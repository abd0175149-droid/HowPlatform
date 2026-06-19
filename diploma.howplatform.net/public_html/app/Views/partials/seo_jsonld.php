<?php
// app/Views/partials/seo_jsonld.php
// ⚠️ أدرج هذا الـPartial في الواجهة العامة فقط (وليس صفحات الأدمن)
?>
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"Organization",
  "name":"منصة كيف التعليمية",
  "alternateName":"Kayf Educational Platform",
  "url":"<?= site_url('/') ?>",
  "logo":"<?= base_url('assets/images/Logo_how_gr.svg') ?>",
  "sameAs":[
    "https://www.facebook.com/your-page",
    "https://www.instagram.com/your-page",
    "https://www.youtube.com/@your-channel",
    "https://www.linkedin.com/company/your-page"
  ],
  "contactPoint":[
    {
      "@type":"ContactPoint",
      "contactType":"customer support",
      "availableLanguage":["ar"],
      "url":"<?= site_url('auth/contact') ?>"
    }
  ]
}
</script>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebSite",
  "name":"منصة كيف التعليمية",
  "url":"<?= site_url('/') ?>",
  "inLanguage":"ar",
  "potentialAction":{
    "@type":"SearchAction",
    "target":"<?= site_url('search') ?>?q={search_term_string}",
    "query-input":"required name=search_term_string"
  }
}
</script>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"ItemList",
  "itemListElement":[
    {"@type":"SiteNavigationElement","position":1,"name":"تسجيل الدخول","url":"<?= site_url('auth/login') ?>"},
    {"@type":"SiteNavigationElement","position":2,"name":"إنشاء حساب","url":"<?= site_url('auth/register') ?>"},
    {"@type":"SiteNavigationElement","position":3,"name":"الأسئلة الشائعة","url":"<?= site_url('auth/faq') ?>"},
    {"@type":"SiteNavigationElement","position":4,"name":"سياسة الخصوصية","url":"<?= site_url('auth/privacy') ?>"},
    {"@type":"SiteNavigationElement","position":5,"name":"الشروط والأحكام","url":"<?= site_url('auth/terms') ?>"},
    {"@type":"SiteNavigationElement","position":6,"name":"تواصل معنا","url":"<?= site_url('auth/contact') ?>"}
  ]
}
</script>
