<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"Organization",
  "name":"منصة كيف التعليمية",
  "alternateName":"HOWPlatform",
  "url":"{{ config('app.url') }}",
  "inLanguage":"ar-JO",
  "logo":"{{ url(asset('assets/images/logo.png')) }}",
  "contactPoint": [{
    "@type": "ContactPoint",
    "contactType": "customer support",
    "email": "support@howplatform.net",
    "availableLanguage": ["ar","en"],
    "url": "{{ config('app.url') }}/contact"
  }],
  "address": {
    "@type": "PostalAddress",
    "addressCountry": "JO",
    "addressLocality": "Amman"
  },
  "sameAs":[
    "https://www.facebook.com/people/%D9%85%D9%86%D8%B5%D8%A9-%D9%83%D9%8A%D9%81-%D8%A7%D9%84%D8%AA%D8%B9%D9%84%D9%8A%D9%85%D9%8A%D8%A9-%D8%A7%D9%84%D8%AF%D8%A8%D9%84%D9%88%D9%85-%D8%A7%D9%84%D8%B9%D8%A7%D9%84%D9%8A/61579341362175/",
    "https://t.me/howplatform_diploma",
    "https://wa.me/962782550901"
  ]
}
</script>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"WebSite",
  "name":"منصة كيف التعليمية",
  "alternateName":"HOWPlatform",
  "url":"{{ config('app.url') }}",
  "inLanguage":"ar-JO",
  "potentialAction":{
    "@type":"SearchAction",
    "target":"{{ rtrim(config('app.url'), '/') }}/search?q={q}",
    "query-input":"required name=q"
  }
}
</script>
