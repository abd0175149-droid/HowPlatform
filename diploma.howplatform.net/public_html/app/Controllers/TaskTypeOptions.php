<?php
namespace App\Controllers\Admins;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Database\Exceptions\DatabaseException;
use App\Models\TaskTypeModel;

class TaskTypeOptions extends Controller
{
    use ResponseTrait;

    private TaskTypeModel $typeModel;

    public function __construct()
    {
        $this->typeModel = new TaskTypeModel();
    }

    /** صفحة HTML (تُستخدم فقط إذا ربطتها في الراوتر مباشرة)
     * ملاحظة: لو عم تحمل via /admins/pages/task_type_options فممكن تستغني عنها.
     */
    public function index()
    {
        return view('admins/pages/task_type_options', [
            'types'      => $this->typeModel->orderBy('name', 'ASC')->findAll(),
            'typesCount' => $this->typeModel->countAllResults(),
        ]);
    }

    /** API: الإحصاءات */
    public function summary()
    {
        return $this->respond(['count' => $this->typeModel->countAllResults()]);
    }

    /** API: إنشاء نوع مهمة */
    public function create()
    {
        // يدعم JSON أو form-url-encoded احتياطاً
        $data = $this->request->getJSON(true) ?: $this->request->getPost();

        if (! $this->validateData($data, ['name' => 'required|is_unique[task_types.name]'])) {
            return $this->failValidationErrors($this->validator->getErrors()); // 422
        }

        try {
            $id = $this->typeModel->insert(['name' => trim($data['name'])], true);
        } catch (DatabaseException $e) {
            // التقاط كود/نص الخطأ من الاستثناء الأصلي لو وُجد
            $prev = $e->getPrevious();
            $code = (int)($e->getCode() ?: ($prev?->getCode() ?? 0));
            $msg  = ($prev?->getMessage() ?? $e->getMessage());

            if ($code === 1062 || stripos($msg, 'Duplicate') !== false) {
                return $this->respond(['error' => 'اسم نوع المهمّة موجود بالفعل'], 409);
            }
            throw $e;
        }

        return $this->respondCreated(['id' => $id, 'name' => $data['name']]);
    }

    /** API: حذف نوع مهمة */
    public function delete(int $id)
    {
        if (! $this->typeModel->find($id)) {
            return $this->failNotFound('Task-type not found');
        }

        try {
            $this->typeModel->delete($id, true); // true: يحذف نهائيًا حتى مع SoftDelete
        } catch (DatabaseException $e) {
            $prev = $e->getPrevious();
            $code = (int)($e->getCode() ?: ($prev?->getCode() ?? 0));
            $msg  = ($prev?->getMessage() ?? $e->getMessage());

            // 1451: قيود FK
            if ($code === 1451 || stripos($msg, 'foreign key constraint fails') !== false) {
                return $this->respond(['error' => 'لا يمكن حذف هذا النوع لأنه مرتبط بمهمات موجودة'], 409);
            }
            throw $e;
        }

        return $this->respond(['deleted' => true]); // 200 + body
    }
}
