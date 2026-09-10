{{-- Modal ubah data master (generik).
    Variabel: $id, $title, $action, $fields (array field definition), $modalClass (opsional) --}}
<div class="modal fade {{ $modalClass ?? '' }}" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 14px">
            <form method="POST" action="{{ $action }}">
                @csrf
                @method('PUT')

                <div class="modal-header" style="border-bottom: 1px solid #e5e7eb">
                    <h5 class="modal-title fw-bold" id="{{ $id }}Label" style="font-size: 15px; color: #143647">
                        <i class="bi bi-pencil-square me-1"></i> {{ $title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">
                    @foreach ($fields as $field)
                        @if (($field['type'] ?? '') === 'checkbox')
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="{{ $field['name'] }}" value="1"
                                       id="{{ $id }}_{{ $field['name'] }}" {{ ($field['checked'] ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="{{ $id }}_{{ $field['name'] }}">{{ $field['label'] }}</label>
                            </div>
                        @else
                            <div class="mb-3">
                                <label class="form-label" for="{{ $id }}_{{ $field['name'] }}">
                                    {{ $field['label'] }} @if ($field['required'] ?? false)<span class="text-danger">*</span>@endif
                                </label>

                                @if (($field['type'] ?? '') === 'select')
                                    <select class="form-select" name="{{ $field['name'] }}" id="{{ $id }}_{{ $field['name'] }}"
                                            @if ($field['required'] ?? false) required @endif>
                                        @if (isset($field['placeholder']))
                                            <option value="">{{ $field['placeholder'] }}</option>
                                        @endif
                                        @foreach ($field['options'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}"
                                                    {{ (string) old($field['name'], $field['value'] ?? '') === (string) $option['value'] ? 'selected' : '' }}>
                                                {{ $option['label'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif (($field['type'] ?? '') === 'textarea')
                                    <textarea class="form-control" name="{{ $field['name'] }}" id="{{ $id }}_{{ $field['name'] }}"
                                              rows="2">{{ old($field['name'], $field['value'] ?? '') }}</textarea>
                                @else
                                    <input type="{{ $field['type'] ?? 'text' }}" class="form-control"
                                           name="{{ $field['name'] }}" id="{{ $id }}_{{ $field['name'] }}"
                                           value="{{ old($field['name'], $field['value'] ?? '') }}"
                                           @if ($field['required'] ?? false) required @endif>
                                @endif

                                @error($field['name'])
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="modal-footer" style="border-top: 1px solid #e5e7eb">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-osdmrb btn-sm px-4">
                        <i class="bi bi-save"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
