@php
    $levelColors = [
        'KEMENTERIAN' => 'org-node--kementerian',
        'ES_I' => 'org-node--es1',
        'ES_II' => 'org-node--es2',
        'ES_III' => 'org-node--es3',
        'BALAI' => 'org-node--balai',
        'LAINNYA' => 'org-node--balai',
    ];
    $levelLabels = [
        'KEMENTERIAN' => 'Puncak',
        'ES_I' => 'Eselon I',
        'ES_II' => 'Eselon II',
        'ES_III' => 'Eselon III',
        'BALAI' => 'Balai / UPT',
        'LAINNYA' => 'Unit',
    ];
    $nodeUnit = $node['unit'];
@endphp

<div class="org-branch">
    <div class="org-node {{ $levelColors[$nodeUnit->level] ?? 'org-node--balai' }} {{ $isRoot ?? false ? 'org-node--root' : '' }}">
        <span class="org-node-level">{{ $levelLabels[$nodeUnit->level] ?? 'Unit' }}</span>
        <strong>{{ $nodeUnit->name }}</strong>
        @if (!empty($nodeUnit->code) && $nodeUnit->code !== 'ROOT')
            <span class="org-node-code">{{ $nodeUnit->code }}</span>
        @endif
    </div>

    @if ($node['children']->isNotEmpty())
        <div class="org-children">
            @foreach ($node['children'] as $child)
                @include('modules.partials.unit-node', ['node' => $child])
            @endforeach
        </div>
    @endif
</div>
