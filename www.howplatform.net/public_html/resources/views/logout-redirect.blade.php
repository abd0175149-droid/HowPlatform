<form id="logout-form" method="get" action="https://auth.howplatform.net/logout-everywhere">
    @csrf
    <input type="hidden" name="redirect" value="{{ $redirect }}">
</form>

<script>
    document.getElementById('logout-form').submit();
</script>
