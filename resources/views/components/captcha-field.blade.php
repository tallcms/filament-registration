@php
    $provider = $getProvider();
    $tokenField = $getTokenField();
    $statePath = $getStatePath();
@endphp

@if ($provider->isEnabled())
    <x-dynamic-component
        :component="$getFieldWrapperView()"
        :field="$field"
    >
        <div class="filament-registration-captcha">
            {!! $provider->renderSnippet() !!}

            {{-- Livewire-bound hidden input the provider's JS writes into via $wire.set(). --}}
            <input
                type="hidden"
                name="{{ $tokenField }}"
                wire:model="{{ $statePath }}"
            />
        </div>
    </x-dynamic-component>
@endif
