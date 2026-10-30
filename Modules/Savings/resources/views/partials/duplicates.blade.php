@if (session('savings_duplicates'))
    <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 space-y-2 text-sm">
        @foreach (session('savings_duplicates') as $match)
            <p>• <a href="{{ route('savings.transactions.show', $match['uuid']) }}" target="_blank" class="font-semibold text-brand">{{ $match['date'] }} · {{ $match['description'] }} · {{ $match['amount'] }}</a></p>
        @endforeach
        <input type="hidden" name="allow_duplicate" value="0">
        <x-common.checkbox name="allow_duplicate" value="1" :label="__('savings::savings.record_anyway')" />
    </div>
@endif
