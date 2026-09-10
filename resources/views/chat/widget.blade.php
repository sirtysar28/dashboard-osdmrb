{{-- ================= WIDGET PESAN / CHAT PRIVATE =================
     Tombol melayang di POJOK KANAN BAWAH semua halaman (khusus login).
     - Klik tombol  : buka panel daftar kontak pegawai (dengan pencarian,
                      pesan terakhir & badge belum dibaca).
     - Klik kontak  : buka percakapan private; pesan dikirim lewat AJAX,
                      riwayat dimuat otomatis (polling tiap beberapa detik). --}}
<div class="chat-widget" id="chatWidget" data-me="{{ auth()->id() }}">

    {{-- ====== TOMBOL MELAYANG ====== --}}
    <button type="button" class="chat-fab" id="chatFab" aria-label="Buka pesan">
        <i class="bi bi-chat-dots-fill chat-fab-icon-open"></i>
        <i class="bi bi-x-lg chat-fab-icon-close"></i>
        <span class="chat-fab-badge" id="chatFabBadge" hidden>0</span>
    </button>

    {{-- ====== PANEL ====== --}}
    <div class="chat-panel" id="chatPanel" role="dialog" aria-label="Pesan private" hidden>

        {{-- ---------- TAMPILAN DAFTAR KONTAK ---------- --}}
        <div class="chat-contacts" id="chatContactsView">
            <div class="chat-panel-header">
                <div class="d-flex align-items-center gap-2 min-width-0">
                    <i class="bi bi-chat-dots-fill"></i>
                    <div class="min-width-0">
                        <strong>Pesan</strong>
                        <small>Chat private antar pegawai</small>
                    </div>
                </div>
            </div>

            <div class="chat-search">
                <i class="bi bi-search"></i>
                <input type="text" id="chatSearchInput" placeholder="Cari nama pegawai..."
                       autocomplete="off" maxlength="60">
            </div>

            <div class="chat-contact-list" id="chatContactList">
                <div class="chat-loading"><span class="spinner-border spinner-border-sm"></span> Memuat daftar pegawai...</div>
            </div>
        </div>

        {{-- ---------- TAMPILAN PERCAKAPAN ---------- --}}
        <div class="chat-conversation" id="chatConversationView" hidden>
            <div class="chat-panel-header">
                <button type="button" class="chat-back" id="chatBackBtn" aria-label="Kembali ke daftar kontak">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <div class="chat-partner" id="chatPartnerInfo">
                    <div class="chat-partner-avatar" id="chatPartnerAvatar">?</div>
                    <div class="min-width-0">
                        <strong id="chatPartnerName">-</strong>
                        <small id="chatPartnerRole">-</small>
                    </div>
                </div>
            </div>

            <div class="chat-messages" id="chatMessages">
                <div class="chat-loading"><span class="spinner-border spinner-border-sm"></span> Memuat pesan...</div>
            </div>

            <form class="chat-compose" id="chatComposeForm" data-no-loader autocomplete="off">
                <input type="text" id="chatMessageInput" placeholder="Tulis pesan..."
                       autocomplete="off" maxlength="2000">
                <button type="submit" id="chatSendBtn" aria-label="Kirim pesan" disabled>
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
        </div>

    </div>
</div>

@once
    @push('scripts')
    <script>
        (function () {
            var widget  = document.getElementById('chatWidget');
            var fab     = document.getElementById('chatFab');
            var panel   = document.getElementById('chatPanel');
            var badge   = document.getElementById('chatFabBadge');

            var contactsView = document.getElementById('chatContactsView');
            var convView     = document.getElementById('chatConversationView');
            var contactList  = document.getElementById('chatContactList');
            var searchInput  = document.getElementById('chatSearchInput');

            var backBtn      = document.getElementById('chatBackBtn');
            var partnerName  = document.getElementById('chatPartnerName');
            var partnerRole  = document.getElementById('chatPartnerRole');
            var partnerAvi   = document.getElementById('chatPartnerAvatar');
            var messagesBox  = document.getElementById('chatMessages');
            var composeForm  = document.getElementById('chatComposeForm');
            var messageInput = document.getElementById('chatMessageInput');
            var sendBtn      = document.getElementById('chatSendBtn');

            var state = {
                open: false,          // panel terbuka?
                partnerId: null,      // lawan bicara aktif
                partnerName: '',
                sending: false,
                signature: null,      // "id terakhir:status dibaca" agar tahu kapan render ulang
                searchTimer: null,
                pollTimer: null,
                unreadTimer: null
            };

            /* ---------- util ---------- */
            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text == null ? '' : String(text);
                return div.innerHTML;
            }

            function fetchJson(url, options) {
                return fetch(url, Object.assign({
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }, options || {})).then(function (res) {
                    return res.json().then(function (data) {
                        if (! res.ok) throw data;
                        return data;
                    });
                });
            }

            /* ---------- badge belum dibaca ---------- */
            function refreshUnread() {
                fetchJson('{{ route('chat.unread') }}').then(function (data) {
                    var total = data.total || 0;
                    badge.textContent = total > 99 ? '99+' : total;
                    badge.hidden = total === 0;
                }).catch(function () {});
            }

            /* ---------- daftar kontak ---------- */
            function renderContacts(contacts) {
                if (! contacts.length) {
                    contactList.innerHTML = '<div class="chat-empty"><i class="bi bi-person-x"></i>Tidak ada pegawai ditemukan.</div>';
                    return;
                }

                contactList.innerHTML = contacts.map(function (c) {
                    var sub = c.last_message
                        ? (c.last_message.mine ? 'Anda: ' : '') + escapeHtml(c.last_message.text)
                        : (c.position ? escapeHtml(c.position) : escapeHtml(c.role));

                    return '<button type="button" class="chat-contact-item" data-id="' + c.id + '" data-name="' + escapeHtml(c.name) + '">' +
                        '<span class="chat-contact-avatar' + (c.online ? ' online' : '') + '">' + escapeHtml(c.initials || '?') +
                            (c.online ? '<i class="chat-online-dot"></i>' : '') + '</span>' +
                        '<span class="chat-contact-meta">' +
                            '<span class="chat-contact-name">' + escapeHtml(c.name) +
                                (c.online ? ' <em class="chat-online-text">daring</em>' : '') + '</span>' +
                            '<span class="chat-contact-preview">' + sub + '</span>' +
                        '</span>' +
                        '<span class="chat-contact-side">' +
                            (c.last_message ? '<small class="chat-contact-time">' + c.last_message.time + '</small>' : '') +
                            (c.unread > 0 ? '<span class="chat-contact-unread">' + (c.unread > 99 ? '99+' : c.unread) + '</span>' : '') +
                        '</span>' +
                    '</button>';
                }).join('');

                contactList.querySelectorAll('.chat-contact-item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        openConversation(item.dataset.id, item.dataset.name);
                    });
                });
            }

            function loadContacts(q) {
                var url = '{{ route('chat.contacts') }}' + (q ? '?q=' + encodeURIComponent(q) : '');
                fetchJson(url).then(function (data) {
                    renderContacts(data.contacts || []);
                }).catch(function () {
                    contactList.innerHTML = '<div class="chat-empty"><i class="bi bi-wifi-off"></i>Gagal memuat daftar pegawai.</div>';
                });
            }

            /* ---------- percakapan ---------- */
            function messageBubble(msg) {
                return '<div class="chat-msg ' + (msg.mine ? 'mine' : '') + '" data-id="' + msg.id + '">' +
                    '<span class="chat-msg-text">' + escapeHtml(msg.text) + '</span>' +
                    '<span class="chat-msg-meta">' + msg.time +
                        (msg.mine ? ' <i class="bi ' + (msg.read ? 'bi-check-all' : 'bi-check') + '"></i>' : '') +
                    '</span>' +
                '</div>';
            }

            function renderMessages(messages, append) {
                if (! append) {
                    messagesBox.innerHTML = messages.length
                        ? messages.map(messageBubble).join('')
                        : '<div class="chat-empty"><i class="bi bi-chat-square-text"></i>Belum ada pesan. Mulai percakapan dengan mengirim pesan.</div>';
                } else if (messages.length) {
                    messagesBox.querySelector('.chat-empty')?.remove();
                    messagesBox.insertAdjacentHTML('beforeend', messages.map(messageBubble).join(''));
                }

                messagesBox.scrollTop = messagesBox.scrollHeight;
            }

            function loadConversation() {
                if (! state.partnerId) return;

                fetchJson('{{ url('pesan') }}/' + state.partnerId).then(function (data) {
                    if (! state.open || state.partnerId != data.partner.id) return;

                    partnerName.textContent = data.partner.name;
                    partnerRole.textContent = data.partner.position || data.partner.role;
                    partnerAvi.textContent  = data.partner.initials || '?';

                    // render ulang penuh bila ada pesan baru ATAU status "sudah dibaca" berubah
                    // (centang biru), sisanya tidak perlu di-render ulang.
                    var last = data.messages.length ? data.messages[data.messages.length - 1] : null;
                    var signature = last ? last.id + ':' + (last.read ? 1 : 0) : 'empty';

                    if (signature !== state.signature) {
                        state.signature = signature;
                        renderMessages(data.messages, false);
                    }

                    refreshUnread();
                }).catch(function () {});
            }

            function openConversation(id, name) {
                state.partnerId   = id;
                state.partnerName = name || '';
                state.signature   = null;

                contactsView.hidden = true;
                convView.hidden = false;

                messagesBox.innerHTML = '<div class="chat-loading"><span class="spinner-border spinner-border-sm"></span> Memuat pesan...</div>';
                partnerName.textContent = state.partnerName;
                partnerRole.textContent = 'Memuat...';
                partnerAvi.textContent = '?';

                messageInput.value = '';
                sendBtn.disabled = true;

                loadConversation();
                setTimeout(function () { messageInput.focus(); }, 50);
            }

            function backToContacts() {
                state.partnerId = null;
                convView.hidden = true;
                contactsView.hidden = false;
                loadContacts(searchInput.value.trim());
            }

            /* ---------- kirim pesan ---------- */
            composeForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var text = messageInput.value.trim();
                if (! text || ! state.partnerId || state.sending) return;

                state.sending = true;
                sendBtn.disabled = true;
                messageInput.disabled = true;

                fetchJson('{{ url('pesan') }}/' + state.partnerId, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ message: text })
                }).then(function (data) {
                    messageInput.value = '';
                    renderMessages([data.message], true);
                }).catch(function (err) {
                    alert((err && err.message) ? err.message : 'Pesan gagal terkirim. Coba lagi.');
                }).finally(function () {
                    state.sending = false;
                    messageInput.disabled = false;
                    sendBtn.disabled = messageInput.value.trim() === '';
                    messageInput.focus();
                });
            });

            messageInput.addEventListener('input', function () {
                sendBtn.disabled = messageInput.value.trim() === '';
            });

            messageInput.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && ! event.shiftKey) {
                    event.preventDefault();
                    composeForm.requestSubmit();
                }
            });

            /* ---------- buka / tutup panel ---------- */
            function startPolling() {
                stopPolling();
                state.pollTimer = setInterval(function () {
                    if (state.partnerId) loadConversation();
                    else loadContacts(searchInput.value.trim());
                }, 5000);
            }

            function stopPolling() {
                if (state.pollTimer) { clearInterval(state.pollTimer); state.pollTimer = null; }
            }

            function togglePanel(force) {
                state.open = force !== undefined ? force : ! state.open;

                panel.hidden = ! state.open;
                widget.classList.toggle('open', state.open);
                fab.classList.toggle('active', state.open);

                if (state.open) {
                    if (! state.partnerId) loadContacts(searchInput.value.trim());
                    else loadConversation();
                    startPolling();
                    setTimeout(function () {
                        (state.partnerId ? messageInput : searchInput).focus();
                    }, 60);
                } else {
                    stopPolling();
                    backToContactsIfAny();
                }

                refreshUnread();
            }

            function backToContactsIfAny() {
                if (state.partnerId) {
                    state.partnerId = null;
                    convView.hidden = true;
                    contactsView.hidden = false;
                }
            }

            fab.addEventListener('click', function () { togglePanel(); });
            backBtn.addEventListener('click', backToContacts);

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && state.open) togglePanel(false);
            });

            /* ---------- pencarian kontak ---------- */
            searchInput.addEventListener('input', function () {
                clearTimeout(state.searchTimer);
                state.searchTimer = setTimeout(function () {
                    if (! state.partnerId) loadContacts(searchInput.value.trim());
                }, 300);
            });

            /* ---------- badge global (jalan terus, walau panel tertutup) ---------- */
            refreshUnread();
            state.unreadTimer = setInterval(refreshUnread, 15000);
        })();
        </script>
    @endpush
@endonce
