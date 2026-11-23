<x-filament-panels::page>
<div class="pm-import" @if ($running) wire:poll.750ms="tick" @endif>
    @unless ($available)
        <section class="pm-card pm-card--note"><h3>Lipsește exportul catalogului</h3><p>Fișierele trebuie să fie în <code>database/seeders/data/catalog/</code> (vin în pachetul de instalare).</p></section>
    @endunless
    <p class="pm-muted">Exportul a fost extras din magazinul actual (Store API public) pe 23.11.2026: {{ number_format($sourceTotal, 0, ',', '.') }} produse. Importul se poate rula de mai multe ori: actualizează prețurile și stocurile fără să dubleze produsele și nu atinge comenzile.</p>
    <div class="pm-steps">
        <section class="pm-card">
            <h3>1. Categorii, branduri, pagini</h3>
            <p class="pm-muted">{{ $categories }} categorii în magazin. Include paginile legale și redirecturile adreselor vechi.</p>
            <x-filament::button wire:click="taxonomies" :disabled="(bool) $running || ! $available" icon="heroicon-o-folder">Importă</x-filament::button>
        </section>
        <section class="pm-card">
            <h3>2. Produse</h3>
            @php $pct = $sourceTotal ? min(100, round(($running === 'products' ? $offset : $products) * 100 / $sourceTotal)) : 0; @endphp
            <div class="pm-progress"><span style="width: {{ $pct }}%"></span></div>
            <p class="pm-muted">{{ number_format($running === 'products' ? $offset : $products, 0, ',', '.') }} / {{ number_format($sourceTotal, 0, ',', '.') }} produse</p>
            @if ($running === 'products')
                <x-filament::button color="gray" wire:click="stop" icon="heroicon-o-stop">Oprește</x-filament::button>
            @else
                <x-filament::button wire:click="startProducts" :disabled="(bool) $running || ! $available" icon="heroicon-o-cube">Importă produsele</x-filament::button>
            @endif
        </section>
        <section class="pm-card">
            <h3>3. Imagini</h3>
            @php $ipct = $imgTotal ? round($imgLocal * 100 / $imgTotal) : 0; @endphp
            <div class="pm-progress"><span style="width: {{ $ipct }}%"></span></div>
            <p class="pm-muted">{{ number_format($imgLocal, 0, ',', '.') }} / {{ number_format($imgTotal, 0, ',', '.') }} copiate pe server @if ($imgFailed)· {{ $imgFailed }} eșuate @endif</p>
            <p class="pm-muted">Până la copiere, imaginile se afișează de pe pet-market.ro. Copierea trebuie terminată înainte de oprirea site-ului vechi.</p>
            @if ($running === 'images')
                <x-filament::button color="gray" wire:click="stop" icon="heroicon-o-stop">Oprește</x-filament::button>
            @else
                <x-filament::button wire:click="startImages" :disabled="(bool) $running || $imgTotal === 0" icon="heroicon-o-photo">Copiază imaginile</x-filament::button>
            @endif
        </section>
    </div>
    @if ($log)
        <section class="pm-card"><h3>Jurnal</h3><div class="pm-log">@foreach ($log as $line)<div>{{ $line }}</div>@endforeach</div></section>
    @endif
    <section class="pm-card pm-card--hiko">
        <img src="{{ asset('media/personaje/pisica-si-caine.png') }}" alt="" width="128" height="80">
        <p>Pe hosting, imaginile se pot copia și din cron, fără browser deschis: <code>php artisan petmarket:import --image-batch=200</code>. Hiko le cară, Raza le numără.</p>
    </section>
</div>
</x-filament-panels::page>
