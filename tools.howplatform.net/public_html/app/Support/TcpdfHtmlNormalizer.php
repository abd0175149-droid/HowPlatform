<?php
namespace App\Support;
trait TcpdfHtmlNormalizer {
  private function normalizeHtmlForTcpdf(string $html): string {
    if (trim($html) === '') return '';
    $dom = new \DOMDocument('1.0','UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xp = new \DOMXPath($dom);

    foreach ($xp->query('//figure') as $fig){ $wrap=$dom->createElement('div'); while($fig->firstChild){$wrap->appendChild($fig->firstChild);} $fig->parentNode->replaceChild($wrap,$fig); }
    foreach ($xp->query('//figcaption') as $cap){ $p=$dom->createElement('p'); $st=$dom->createElement('strong'); $st->appendChild($cap->firstChild?:$dom->createTextNode('')); $p->appendChild($st); $cap->parentNode->replaceChild($p,$cap); }
    foreach (['h1','h2','h3','h4','h5','h6'] as $h){ foreach ($xp->query("//{$h}") as $el){ $p=$dom->createElement('p'); $st=$dom->createElement('strong'); while($el->firstChild){$st->appendChild($el->firstChild);} $p->appendChild($st); $el->parentNode->replaceChild($p,$el); } }

    $safeStyle=function(string $style): string {
      $out=[]; foreach (explode(';',$style) as $d){ if(!$d)continue; [$k,$v]=array_map('trim',array_pad(explode(':',$d,2),2,'')); $k=strtolower($k);
        if ($k==='text-align' && in_array($v,['right','left','center','justify'],true)) $out[]="$k:$v";
        if ($k==='direction' && in_array($v,['rtl','ltr'],true)) $out[]="$k:$v";
        if ($k==='line-height' && preg_match('/^\d+(\.\d+)?(px|em|rem|%)?$/',$v)) $out[]="$k:$v";
        if ($k==='width' && preg_match('/^\d+(\.\d+)?(px|%)$/',$v)) $out[]="$k:$v";
        if ($k==='height' && preg_match('/^\d+(\.\d+)?(px|%)$/',$v)) $out[]="$k:$v";
        if ($k==='border' && preg_match('/^\d+px( solid)?( #[0-9a-fA-F]{3,6})?$/',$v)) $out[]="$k:$v";
        if ($k==='border-collapse' && in_array(strtolower($v),['collapse','separate'],true)) $out[]="$k:$v";
      } return implode(';',$out);
    };

    foreach ($xp->query('//*') as $el){
      $tag=strtolower($el->nodeName); $orig=[];
      if ($el->hasAttributes()){ foreach (iterator_to_array($el->attributes) as $a){ $orig[strtolower($a->nodeName)]=$a->nodeValue; } }
      while ($el->attributes->length){ $el->removeAttribute($el->attributes->item(0)->nodeName); }

      if ($tag==='a'){ $href=$orig['href']??''; if ($href && preg_match('#^(https?:|mailto:)#i',$href)) $el->setAttribute('href',$href); }
      elseif ($tag==='img'){
        $src=$orig['src']??''; if ($src && preg_match('#^(data:image/[^;]+;base64,|https?://)#i',$src)) $el->setAttribute('src',$src);
        foreach (['width','height'] as $dim){ $v=$orig[$dim]??''; if (preg_match('/^\d+$/',$v)) $el->setAttribute($dim,$v); }
        if (!empty($orig['alt'])) $el->setAttribute('alt',$orig['alt']);
      } elseif (in_array($tag,['table','thead','tbody','tfoot','tr','td','th','colgroup','col'],true)){
        foreach (['rowspan','colspan'] as $att){ $n=isset($orig[$att])?(int)$orig[$att]:0; if ($n>0 && $n<100) $el->setAttribute($att,(string)$n); }
        if ($tag==='table'){ $el->setAttribute('border','1'); $el->setAttribute('cellpadding','4'); $el->setAttribute('cellspacing','0'); }
      }

      $style=$orig['style']??''; if ($style){ $s=$safeStyle($style); if ($s!=='') $el->setAttribute('style',$s); }
    }

    foreach ($xp->query('//span') as $sp){ if(!$sp->hasAttributes()){ $frag=$dom->createDocumentFragment(); while($sp->firstChild){$frag->appendChild($sp->firstChild);} $sp->parentNode->replaceChild($frag,$sp); } }
    foreach ($xp->query('//p') as $p){ $txt=trim(preg_replace('/\x{00A0}/u',' ',$p->textContent??'')); if ($txt===''){ $br=$dom->createElement('br'); $p->parentNode->replaceChild($br,$p); } }

    $out=$dom->saveHTML();
    $out=preg_replace('/(?:<br\s*\/?>\s*){3,}/i','<br><br>',$out);
    $out=preg_replace('/\x{00A0}/u',' ',$out);
    return $out;
  }
}
