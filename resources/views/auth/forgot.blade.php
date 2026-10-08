<x-layouts.guest title="Recuperar acesso">
<span class="eyebrow">SUA CONTA</span><h2>Recuperar acesso</h2><p class="muted">Informe o e-mail cadastrado para receber as instruções.</p>
@if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
@if($errors->any())<div class="notice error" role="alert">{{ $errors->first() }}</div>@endif
<form class="auth-form" method="post" action="{{ route('password.email') }}">@csrf<label for="email">E-mail</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email"><button class="button primary full">Enviar instruções</button></form>
<a class="back-link" href="{{ route('login') }}">Voltar para o acesso</a>
</x-layouts.guest>
