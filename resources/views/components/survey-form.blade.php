{{-- Widget survei masukan & saran aplikasi (dipakai di dashboard & beranda) --}}
<div class="chart-card survey-card" id="surveyCard">
    <h5><i class="bi bi-chat-square-heart me-2"></i>Survei Masukan &amp; Saran</h5>

    <p class="small text-muted mb-3">
        Bagaimana pengalaman Anda menggunakan aplikasi Dashboard Biro OSDMRB?
        Masukan Anda sangat berharga untuk pengembangan aplikasi ini.
    </p>

    <form method="POST" action="{{ route('surveys.store') }}" data-no-loader>
        @csrf

        <div class="mb-3">
            <label class="form-label d-block mb-1">Beri Rating</label>
            <div class="star-rating">
                @foreach (range(5, 1) as $star)
                    <input type="radio" name="rating" value="{{ $star }}" id="star{{ $star }}"
                           {{ old('rating') == $star ? 'checked' : '' }} required>
                    <label for="star{{ $star }}" title="{{ $star }} bintang"><i class="bi bi-star-fill"></i></label>
                @endforeach
            </div>
            @error('rating')
                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="surveyMessage" class="form-label">Masukan &amp; Saran <small class="text-muted fw-normal">(opsional)</small></label>
            <textarea name="message" id="surveyMessage" rows="2" class="form-control form-control-sm"
                      placeholder="Tuliskan masukan, saran, atau kendala yang Anda alami...">{{ old('message') }}</textarea>
            @error('message')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-osdmrb btn-sm px-4" data-loader-text="Mengirim survei">
            <i class="bi bi-send"></i> Kirim Survei
        </button>
    </form>
</div>
