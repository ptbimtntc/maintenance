                {{-- LOCKOUT STATION header, after the reference image: black strip with a hazard stripe along its top and
                     bottom edge (CSS), the title and the board title in yellow on the left, the red danger badge on the
                     right. The board title is shown with a hyphen (MAINTENANCE - TIRE CORD). The subtitle element stays
                     for Edit Mode (it is shown there and read aloud otherwise); the title and subtitle classes are what
                     Edit Mode updates live. --}}
                <header class="lototo-board-heading">
                    <div class="lototo-station-text">
                        <p class="lototo-station-label">LOCKOUT STATION</p>
                        <h1 class="lototo-station-title">{{ str_replace(' · ', ' - ', $board->title) }}</h1>
                        <p class="lototo-station-subtitle">{{ $board->subtitle }}</p>
                    </div>
                    <div class="lototo-danger-badge" role="note">
                        <svg class="lototo-danger-badge__icon" viewBox="0 0 32 28" aria-hidden="true">
                            <path d="M16 2.2 30 26H2z" fill="#facc15" stroke="#0a0a0a" stroke-width="2.4" stroke-linejoin="round"/>
                            <path d="M16 10.2v7" stroke="#0a0a0a" stroke-width="3" stroke-linecap="round"/>
                            <circle cx="16" cy="21.6" r="1.8" fill="#0a0a0a"/>
                        </svg>
                        <span class="lototo-danger-badge__text">
                            <strong class="lototo-danger-badge__title">BAHAYA - JANGAN DILEPAS</strong>
                            <span class="lototo-danger-badge__note">Hanya pemilik gembok yang boleh melepas gemboknya sendiri</span>
                        </span>
                    </div>
                </header>
