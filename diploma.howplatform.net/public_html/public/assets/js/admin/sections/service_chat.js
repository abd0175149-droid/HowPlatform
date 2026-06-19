// public/assets/js/admins/sections/service_chat.js
// Shim → يطابق اسم الـslug الذي يتوقعه admin_router: "service_chat"
// يعيد تصدير الموديول الحقيقي لدينا مع لوج، ويضمن كسر الكاش عبر ?v=

console.info('[service:shim] loading service_requests_chat.js …');

// ⚠️ غيّر رقم النسخة عند كل تعديل لكسر الكاش على Hostinger/CDN:
import ServicePage from './service_requests_chat.js?v=1.0.3';

console.info('[service:shim] loaded OK → exporting default ServicePage');
export default ServicePage;
