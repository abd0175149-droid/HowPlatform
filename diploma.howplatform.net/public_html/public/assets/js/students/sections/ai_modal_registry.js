// /public/assets/js/students/sections/ai_modal_registry.js
(function () {
  const MAP = [
    {
      when: ({ universityId, taskTypeId }) =>
        Number(universityId) === 2 && Number(taskTypeId) === 31,
      module: '/assets/js/students/sections/ai_lesson_plan_modal.js?v=1',
      typeKey: 'lesson_plan_v1'
    }
  ];

  function resolveModalConfig({ universityId, taskTypeId }) {
    const hit = MAP.find(x => x.when({ universityId, taskTypeId }));
    return hit ? { module: hit.module, typeKey: hit.typeKey } : null;
  }

  window.AI_MODAL_REGISTRY = { resolveModalConfig };
})();
