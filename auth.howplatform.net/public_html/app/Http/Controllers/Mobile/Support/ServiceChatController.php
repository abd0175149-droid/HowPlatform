<?php

namespace App\Http\Controllers\Mobile\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ServiceChatController extends Controller
{
    /** كل العمليات على قاعدة الدبلوم */
    private const DIP_CONN = 'diploma';

    private const MAX_FILE_BYTES = 10 * 1024 * 1024;
    private const PAGE_MSG_LIMIT = 10;

    private const ALLOWED_MIME = [
        'image/jpeg','image/png','image/gif','image/webp',
        'video/mp4','video/webm','video/ogg',
        'application/pdf',
        'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/zip','application/x-rar-compressed',
        'audio/mpeg','audio/mp4','audio/ogg','audio/webm','audio/wav','audio/x-m4a'
    ];

    private function dipDb()     { return DB::connection(self::DIP_CONN); }
    private function dipSchema() { return Schema::connection(self::DIP_CONN); }

    private function ok(array $data=[], string $message='OK', int $status=200): JsonResponse
    {
        return response()->json(['success'=>true,'message'=>$message,'data'=>$data], $status);
    }
    private function fail(string $message, int $status=400, array $extra=[]): JsonResponse
    {
        return response()->json(['success'=>false,'message'=>$message]+$extra, $status);
    }

    private function levelToPriority(int $lvl): string
    {
        return $lvl >= 3 ? 'urgent' : ($lvl === 2 ? 'high' : ($lvl === 1 ? 'medium' : 'low'));
    }
    private function normalizeMime(string $m): string { return strtolower(trim($m)); }
    private function isAllowedMime(string $m): bool { return in_array($m, self::ALLOWED_MIME, true); }
    private function classify(string $mime, string $name): string
    {
        $n = strtolower($name);
        if (str_starts_with($n,'voice_') || str_contains($n,'voice') || str_contains($mime,'audio')) {
            return str_contains($n,'voice') ? 'voice_recording' : 'audio';
        }
        return match (true) {
            str_starts_with($mime,'image/') => 'image',
            str_starts_with($mime,'video/') => 'video',
            $mime === 'application/pdf'     => 'pdf',
            str_contains($mime,'msword') || str_contains($mime,'officedocument.wordprocessingml') => 'document',
            str_contains($mime,'excel')  || str_contains($mime,'spreadsheetml')                    => 'spreadsheet',
            str_contains($mime,'powerpoint') || str_contains($mime,'presentationml')              => 'presentation',
            str_contains($mime,'zip') || str_contains($mime,'rar')                                 => 'archive',
            default => 'attachment',
        };
    }

    /** GET /api/mobile/support/top-meta */
    public function topMeta(Request $request): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        // مستوى الخطة من user_subscriptions/plans على نفس اتصال الدبلوم
        if (!$this->dipSchema()->hasTable('user_subscriptions') || !$this->dipSchema()->hasTable('plans')) {
            return $this->ok(['plan_level'=>0,'priority'=>'low','canSendVoice'=>0,'upgrade_suggest'=>'Voice messages need a higher plan.']);
        }

        $lvl = (int) ($this->dipDb()->table('user_subscriptions as us')
            ->join('plans as p','p.id','=','us.plan_id')
            ->where('us.user_id',$uid)
            ->where('us.status','active')
            ->where('us.start_at','<=',now())
            ->where(function($w){ $w->whereNull('us.end_at')->orWhere('us.end_at','>=',now()); })
            ->max('p.level') ?? 0);

        return $this->ok([
            'plan_level'      => $lvl,
            'priority'        => $this->levelToPriority($lvl),
            'canSendVoice'    => $lvl >= 2 ? 1 : 0,
            'upgrade_suggest' => $lvl <= 1 ? 'Voice messages need a higher plan.' : null,
        ]);
    }

    /** GET /api/mobile/support/requests/{reqId}/messages */
    public function list(Request $request, int $reqId): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db = $this->dipDb();
        $exists = $db->table('service_requests')->where(['id'=>$reqId,'user_id'=>$uid])->exists();
        if (!$exists) return $this->fail('Request not accessible', 403);

        $beforeId = (int) $request->query('before_id', 0);
        $limit    = min(50, max(1, (int) $request->query('limit', self::PAGE_MSG_LIMIT)));

        $select = ['sm.id','sm.body','sm.sender_user_id','sm.created_at'];
        $readsEnabled = $this->dipSchema()->hasTable('service_message_reads');
        if ($readsEnabled) {
            $select[] = DB::raw("EXISTS(SELECT 1 FROM service_message_reads r WHERE r.message_id=sm.id AND r.reader_user_id={$uid}) AS is_read");
        } else {
            $select[] = DB::raw("0 AS is_read");
        }

        $qb = $db->table('service_messages as sm')->where('sm.service_request_id',$reqId);
        if ($beforeId > 0) $qb->where('sm.id','<',$beforeId);

        $rowsDesc = $qb->orderBy('sm.id','DESC')->limit($limit+1)->get($select)->toArray();
        $hasMore  = count($rowsDesc) > $limit;
        if ($hasMore) array_pop($rowsDesc);

        $ids = array_map(fn($r)=>$r->id, $rowsDesc);
        $filesByMsg = [];
        if (!empty($ids)) {
            $files = $db->table('service_request_files as srf')
                ->join('files as f','f.id','=','srf.file_id')
                ->whereIn('srf.message_id', $ids)
                ->orderBy('srf.display_order','ASC')
                ->get([
                    'srf.message_id',
                    'f.id as file_id','f.file_name','f.file_path','f.mime_type',
                    DB::raw("(COALESCE(f.file_size_bytes, f.file_size)) as file_size_bytes"),
                    'srf.file_type',
                    DB::raw("CASE WHEN srf.file_type='voice_recording' OR f.mime_type LIKE 'audio/%' THEN 1 ELSE 0 END as is_voice")
                ]);

            foreach ($files as $f) {
                $filesByMsg[$f->message_id][] = $f;
            }
        }

        $rowsAsc = array_reverse($rowsDesc);
        $meName  = trim((string) (optional($request->user())->name ?? 'Me'));
        foreach ($rowsAsc as &$row) {
            $mid  = (int) $row->id;
            $isMe = ((int)$row->sender_user_id === $uid);
            $row->files       = $filesByMsg[$mid] ?? [];
            $row->is_me       = $isMe ? 1 : 0;
            $row->sender_name = $isMe ? $meName : 'فريق الدعم';
            $row->is_read     = (int) $row->is_read;
        }
        unset($row);

        $nextBeforeId = null;
        if (!empty($rowsAsc)) {
            $minId = min(array_map(fn($r)=>$r->id, $rowsAsc));
            $nextBeforeId = $hasMore ? $minId : null;
        }

        return $this->ok([
            'items'          => $rowsAsc,
            'limit'          => $limit,
            'has_more'       => $hasMore,
            'next_before_id' => $nextBeforeId,
        ]);
    }

    /** POST /api/mobile/support/requests/{reqId}/messages */
    public function send(Request $request, int $reqId): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db = $this->dipDb();
        $exists = $db->table('service_requests')->where(['id'=>$reqId,'user_id'=>$uid])->exists();
        if (!$exists) return $this->fail('Request not accessible', 403);

        $body = trim((string) $request->input('body',''));
        $incoming = [];
        if ($request->hasFile('files')) {
            $fs = $request->file('files'); if (!is_array($fs)) $fs = [$fs];
            foreach ($fs as $f) if ($f->isValid()) $incoming[] = $f;
        }
        if ($body === '' && empty($incoming)) return $this->fail('Message body or files required.', 422);

        // منع الصوت لمن هم أقل من المستوى 2 (على نفس اتصال الدبلوم)
        $lvl = 0;
        if ($this->dipSchema()->hasTable('user_subscriptions') && $this->dipSchema()->hasTable('plans')) {
            $lvl = (int) ($db->table('user_subscriptions as us')
                ->join('plans as p','p.id','=','us.plan_id')
                ->where('us.user_id',$uid)
                ->where('us.status','active')
                ->where('us.start_at','<=',now())
                ->where(function($w){ $w->whereNull('us.end_at')->orWhere('us.end_at','>=',now()); })
                ->max('p.level') ?? 0);
        }

        if ($lvl < 2 && !empty($incoming)) {
            foreach ($incoming as $f) {
                $mime  = $this->normalizeMime($f->getMimeType() ?: '');
                $class = $this->classify($mime, $f->getClientOriginalName());
                if ($class === 'voice_recording' || $class === 'audio') {
                    return $this->fail('Audio uploads require an upgraded plan.', 403);
                }
            }
        }

        $db->beginTransaction();
        try {
            $msgId = $db->table('service_messages')->insertGetId([
                'service_request_id' => $reqId,
                'sender_user_id'     => $uid,
                'body'               => $body !== '' ? $body : null,
                'created_at'         => now(),
            ]);

            if (!empty($incoming)) {
                $dir = "service_requests/{$reqId}/messages/{$msgId}";
                $order = 0;

                foreach ($incoming as $file) {
                    if ($file->getSize() > self::MAX_FILE_BYTES) continue;

                    $mime = $this->normalizeMime($file->getMimeType() ?: '');
                    if (!$this->isAllowedMime($mime)) continue;

                    $new = bin2hex(random_bytes(10)).'.'.$file->getClientOriginalExtension();
                    $path = Storage::disk('local')->putFileAs($dir, $file, $new);

                    $fileType = $this->classify($mime, $file->getClientOriginalName());

                    $hasSizeBytes = $this->dipSchema()->hasColumn('files','file_size_bytes');
                    $hasUploader  = $this->dipSchema()->hasColumn('files','uploader_id');
                    $hasUploadedBy= $this->dipSchema()->hasColumn('files','uploaded_by');

                    $filesRow = [
                        'file_name'  => $file->getClientOriginalName(),
                        'file_path'  => Storage::disk('local')->path($path),
                        'mime_type'  => $mime,
                        ($hasSizeBytes ? 'file_size_bytes':'file_size') => (int) $file->getSize(),
                        ($hasUploader ? 'uploader_id':'uploaded_by')     => $uid,
                        'uploaded_at' => now(),
                    ];
                    $db->table('files')->insert($filesRow);
                    $fileId = (int) $db->getPdo()->lastInsertId();

                    $db->table('service_request_files')->insert([
                        'service_request_id' => $reqId,
                        'message_id'         => $msgId,
                        'file_id'            => $fileId,
                        'file_type'          => $fileType,
                        'display_order'      => $order++,
                    ]);
                }
            }

            $db->commit();
            return $this->ok(['message_id'=>$msgId], 'Message sent.');
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->fail('Failed to send message.', 500, ['ref'=>'SC-SND-500']);
        }
    }

    /** PATCH /api/mobile/support/requests/{reqId}/messages/{msgId} */
    public function edit(Request $request, int $reqId, int $msgId): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db = $this->dipDb();

        $exists = $db->table('service_requests')->where(['id'=>$reqId,'user_id'=>$uid])->exists();
        if (!$exists) return $this->fail('Request not accessible', 403);

        $msg = $db->table('service_messages')->where(['id'=>$msgId,'service_request_id'=>$reqId])->first(['id','sender_user_id']);
        if (!$msg) return $this->fail('Message not found', 404);
        if ((int)$msg->sender_user_id !== $uid) return $this->fail('You can only edit your own messages', 403);

        $body = trim((string) $request->input('body',''));
        if (mb_strlen($body) < 1) return $this->fail('Body required', 422);

        $db->table('service_messages')->where('id',$msgId)->update(['body'=>$body]);
        return $this->ok([], 'Message updated.');
    }

    /** DELETE /api/mobile/support/requests/{reqId}/messages/{msgId} */
    public function delete(Request $request, int $reqId, int $msgId): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db = $this->dipDb();

        $exists = $db->table('service_requests')->where(['id'=>$reqId,'user_id'=>$uid])->exists();
        if (!$exists) return $this->fail('Request not accessible', 403);

        $msg = $db->table('service_messages')->where(['id'=>$msgId,'service_request_id'=>$reqId])->first(['id','sender_user_id']);
        if (!$msg) return $this->fail('Message not found', 404);
        if ((int)$msg->sender_user_id !== $uid) return $this->fail('You can only delete your own messages', 403);

        $db->beginTransaction();
        try {
            $links = $db->table('service_request_files')->where('message_id',$msgId)->get(['id','file_id']);
            foreach ($links as $lnk) {
                $db->table('service_request_files')->where('id',$lnk->id)->delete();

                $refs = $db->table('service_request_files')->where('file_id',$lnk->file_id)->count();
                if ($refs === 0) {
                    $file = $db->table('files')->where('id',$lnk->file_id)->first(['id','file_path']);
                    if ($file) {
                        if ($file->file_path && file_exists($file->file_path)) @unlink($file->file_path);
                        $db->table('files')->where('id',$file->id)->delete();
                    }
                }
            }

            $db->table('service_messages')->where('id',$msgId)->delete();
            $db->commit();

            return $this->ok([], 'Message deleted.');
        } catch (\Throwable $e) {
            $db->rollBack();
            return $this->fail('Server error during delete', 500, ['ref'=>'SC-DEL-500']);
        }
    }

    /** POST /api/mobile/support/requests/{reqId}/read-up-to */
    public function readUpTo(Request $request, int $reqId): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        $db = $this->dipDb();

        $exists = $db->table('service_requests')->where(['id'=>$reqId,'user_id'=>$uid])->exists();
        if (!$exists) return $this->fail('Request not accessible', 403);

        if (!$this->dipSchema()->hasTable('service_message_reads')) {
            return $this->ok(['inserted'=>0,'last_id'=>null,'unread_after'=>0]);
        }

        $lastId = (int) ($request->input('last_id') ?? $request->input('last_message_id') ?? 0);
        if ($lastId <= 0) {
            $lastId = (int) ($db->table('service_messages')
                ->where('service_request_id',$reqId)
                ->where('sender_user_id','<>',$uid)
                ->max('id') ?? 0);
        }

        if ($lastId <= 0) {
            return $this->ok(['inserted'=>0,'last_id'=>null,'unread_after'=>0]);
        }

        // INSERT IGNORE (MySQL)
        $sql = "
            INSERT IGNORE INTO service_message_reads (message_id, reader_user_id, read_at)
            SELECT sm.id, ?, NOW()
              FROM service_messages sm
         LEFT JOIN service_message_reads r ON r.message_id = sm.id AND r.reader_user_id = ?
             WHERE sm.service_request_id = ?
               AND sm.sender_user_id <> ?
               AND sm.id <= ?
               AND r.message_id IS NULL
        ";
        $db->statement($sql, [$uid,$uid,$reqId,$uid,$lastId]);

        $remain = $db->table('service_messages as sm')
            ->leftJoin('service_message_reads as r', function($j) use($uid){
                $j->on('r.message_id','=','sm.id')->where('r.reader_user_id','=',$uid);
            })
            ->where('sm.service_request_id',$reqId)
            ->where('sm.sender_user_id','<>',$uid)
            ->whereNull('r.message_id')
            ->count();

        return $this->ok([
            'inserted'     => null,
            'last_id'      => $lastId,
            'unread_after' => $remain,
        ]);
    }

    /** GET /api/mobile/support/unread-counters */
    public function unreadCounters(Request $request): JsonResponse
    {
        $uid = (int) ($request->user()->id ?? 0);
        if ($uid <= 0) return $this->fail('Unauthenticated', 401);

        if (!$this->dipSchema()->hasTable('service_message_reads')) {
            $rows = $this->dipDb()->table('service_requests')
                ->where('user_id',$uid)->get(['id as request_id', DB::raw('0 as unread_count')]);
            return $this->ok(['counters'=>$rows]);
        }

        $sql = "
            SELECT sr.id AS request_id,
                   (SELECT COUNT(*)
                      FROM service_messages sm
                 LEFT JOIN service_message_reads r
                        ON r.message_id = sm.id AND r.reader_user_id = ?
                     WHERE sm.service_request_id = sr.id
                       AND sm.sender_user_id <> ?
                       AND r.message_id IS NULL) AS unread_count
              FROM service_requests sr
             WHERE sr.user_id = ?
        ";
        $rows = $this->dipDb()->select($sql, [$uid,$uid,$uid]);
        return $this->ok(['counters'=>$rows]);
    }
}
