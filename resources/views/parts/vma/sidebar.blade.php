<section class="widget">
    <h2>VMA Navigation</h2>
    <ul>
        <li><a href="{{ route('vma-overview') }}">Aktuella och tidigare VMA</a></li>
        <li><a href="{{ route('vma-textpage', ['slug' => 'om-vma']) }}">Vad är VMA?</a></li>
        <li><a href="{{ route('vma-textpage', ['slug' => 'vanliga-fragor-och-svar-om-vma']) }}">Vanliga frågor & svar</a>
        </li>
    </ul>
</section>

{{-- @include('parts.lan-and-cities')
@include('parts.follow-us') --}}
