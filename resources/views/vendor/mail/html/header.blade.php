<!-- resources/views/vendor/mail/html/header.blade.php -->

<tr>
<td class="header">
    <a href="{{ $url }}" style="display:inline-block;text-decoration:none;">

        <!-- Logo image -->
        <img
            src="{{ config('app.url') }}/build/assets/primegest.png"
            class="logo"
            alt="PrimeGest"
        >

        <!-- Nom de l'app -->
        <span style="
            display: block;
            margin-top: 10px;
            font-size: 20px;
            font-weight: 700;
            color: #1A56A0;
            letter-spacing: 0.01em;
        ">PrimeGest</span>

        <!-- Tagline optionnelle -->
        <span style="
            display: block;
            font-size: 11px;
            color: #7A6248;
            margin-top: 4px;
            letter-spacing: 0.04em;
        ">Gestion commerciale intelligente</span>

    </a>
</td>
</tr>