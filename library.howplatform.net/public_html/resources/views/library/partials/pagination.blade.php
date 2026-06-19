@if($resources->hasPages())
  <div class="pagination-wrap" id="pagination">
    {{-- لا نحدد اسم الـ view هنا؛ نخليها تعتمد القيم الافتراضية --}}
    {{ $resources->withQueryString()->onEachSide(1)->links() }}
  </div>
@else
  <div class="pagination-wrap" id="pagination"></div>
@endif
