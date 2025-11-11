// ============================================
// BROWSE & SEARCH FUNCTIONALITY - UPDATED
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    const searchBox = document.getElementById('searchBox');
    const categoryFilter = document.getElementById('categoryFilter');
    const servicesContainer = document.getElementById('servicesContainer');
    const rateTypeFilter = document.getElementById('rateTypeFilter');
    const rateRangeFilter = document.getElementById('rateRangeFilter');

    // Debounce function
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // Update price label based on rate type
    function updatePriceLabel() {
        const type = rateTypeFilter?.value;
        const options = rateRangeFilter?.options;
        if (!options) return;

        for (let opt of options) {
            if (opt.value === 'all') {
                opt.text = 'Any Price';
                continue;
            }
            let text = opt.text.split(' ')[0];
            if (type === 'hourly') text += '/hr';
            else if (type === 'daily') text += '/day';
            else if (type === 'fixed') text = opt.text.replace(/\/hr.*/, ' (fixed)');
            else text = opt.text.replace(/\/hr.*$/, '');
            opt.text = text;
        }
    }

    rateTypeFilter?.addEventListener('change', () => {
        updatePriceLabel();
        performSearch();
    });
    updatePriceLabel();

    // Perform search
    async function performSearch() {
        const query = searchBox.value.trim();
        const category = categoryFilter.value;
        const city = document.getElementById('cityFilter')?.value || 'all';
        const rateType = rateTypeFilter?.value || 'all';
        const rateRange = rateRangeFilter?.value || 'all';

        try {
            if (servicesContainer) {
                servicesContainer.innerHTML = `
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p style="color: #667eea; font-weight: 600;">Searching for services...</p>
                    </div>
                `;
            }
            
            const url = `client.php?ajax=1&action=search_skills&q=${encodeURIComponent(query)}&category=${encodeURIComponent(category)}&city=${encodeURIComponent(city)}&rate_type=${encodeURIComponent(rateType)}&rate_range=${encodeURIComponent(rateRange)}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.ok) {
                renderResults(data.data);
            } else {
                console.error('Search failed:', data.error);
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }

    // Render search results with NEW design
    function renderResults(skills) {
        if (!servicesContainer) return;

        if (skills.length === 0) {
            servicesContainer.innerHTML = '<div class="empty-state">No services found matching your criteria.</div>';
            return;
        }

        servicesContainer.innerHTML = skills.map(p => {
            const locationParts = [p.Barangay, p.City, p.Province].filter(Boolean);
            const location = locationParts.length > 0 
                ? locationParts.join(', ') 
                : (p.Location || 'Unknown');

            const description = p.Description || '';
            const shortDesc = description.length > 150 
                ? description.substring(0, 150) + '...' 
                : description;

            // Generate unique ID for availability container
            const availId = `avail_${p.SkillID}_${Date.now()}`;

            return `
                <div class="provider-card"
                    data-city="${escapeHtml(p.City || '')}"
                    data-province="${escapeHtml(p.Province || '')}"
                    data-barangay="${escapeHtml(p.Barangay || '')}"
                    data-skill-id="${parseInt(p.SkillID) || 0}"
                    data-provider-id="${parseInt(p.UserID) || 0}">

                    <div class="provider-header">
                        <h3>${escapeHtml((p.FName || '') + ' ' + (p.LName || ''))}</h3>
                        <span class="category-badge">${escapeHtml(p.SkillName || 'Other')}</span>
                    </div>
                    
                    <div class="provider-details">
                        <p><strong>📍 Location:</strong> ${escapeHtml(location)}</p>
                        <p class="rate-highlight">
                            <strong>💰 Rate:</strong> PHP ${parseFloat(p.Rate || 0).toFixed(2)}
                            ${p.RateType === 'daily' ? '/day' : (p.RateType === 'fixed' ? ' (fixed)' : '/hour')}
                        </p>
                    </div>

                    <p class="card-description">${escapeHtml(shortDesc)}</p>

                    <button class="availability-toggle" data-target="${availId}">
                        <span>📅 View Availability</span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="currentColor">
                            <path d="M2 4L6 8L10 4"/>
                        </svg>
                    </button>

                    <div class="availability-container" id="${availId}">
                        <div class="availability-loading" style="text-align: center; padding: 12px; color: white;">
                            Loading availability...
                        </div>
                    </div>

                    <div class="card-actions">
                        ${description.length > 150 ? `
                            <button class="btn-secondary read-more-btn" 
                                    data-description="${escapeHtml(description)}">
                                Read More
                            </button>
                        ` : ''}
                        <form method="POST" class="book-form" style="display: contents;">
                            <input type="hidden" name="book_skill_id" value="${parseInt(p.SkillID) || 0}">
                            <input type="hidden" name="provider_id" value="${parseInt(p.UserID) || 0}">
                            <input type="text" class="flatpickr-input" placeholder="📅 Pick date & time" required readonly>
                            <input type="hidden" name="preferred_schedule" value="">
                            <button type="submit" class="btn-primary book-btn">Book Now</button>
                        </form>
                    </div>
                </div>
            `;
        }).join('');

        reattachEventListeners();
        setTimeout(() => initializeDatePickers(), 100);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Reattach event listeners
    function reattachEventListeners() {
        // Read More buttons
        document.querySelectorAll('.read-more-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const description = this.getAttribute('data-description');
                const fullDescription = document.getElementById('fullDescription');
                if (fullDescription) {
                    fullDescription.innerHTML = description.replace(/\n/g, '<br>');
                    const modal = document.getElementById('descModal');
                    if (modal) {
                        modal.classList.add('show');
                        document.body.style.overflow = 'hidden';
                    }
                }
            });
        });

        // ✨ NEW: Availability Toggle
        document.querySelectorAll('.availability-toggle').forEach(btn => {
            btn.addEventListener('click', async function() {
                const targetId = this.getAttribute('data-target');
                const container = document.getElementById(targetId);
                const card = this.closest('.provider-card');
                const providerId = card.getAttribute('data-provider-id');
                
                // Toggle expanded state
                const isExpanded = container.classList.contains('expanded');
                
                if (isExpanded) {
                    container.classList.remove('expanded');
                    this.classList.remove('active');
                } else {
                    // Close other expanded availability displays
                    document.querySelectorAll('.availability-container.expanded').forEach(c => {
                        c.classList.remove('expanded');
                    });
                    document.querySelectorAll('.availability-toggle.active').forEach(b => {
                        b.classList.remove('active');
                    });
                    
                    container.classList.add('expanded');
                    this.classList.add('active');
                    
                    // Load availability if not already loaded
                    if (!container.dataset.loaded) {
                        await loadAvailability(providerId, container);
                        container.dataset.loaded = 'true';
                    }
                }
            });
        });

        // Book forms
        document.querySelectorAll('.book-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const hidden = form.querySelector('input[name="preferred_schedule"]');
                const input = form.querySelector('.flatpickr-input');
                
                if (!hidden || !hidden.value) {
                    if (input) input.focus();
                    if (typeof showToast === 'function') {
                        showToast('Please select a date and time first', 'error');
                    } else {
                        alert('Please select a date and time first');
                    }
                    return;
                }
                
                const card = this.closest(".provider-card");
                if (card) {
                    card.style.transition = "opacity 0.4s ease, transform 0.4s ease";
                    card.style.opacity = "0.6";
                    card.style.transform = "translateY(4px)";
                }
                
                const formData = new FormData(this);
                const skillId = formData.get('book_skill_id');
                const schedule = formData.get('preferred_schedule');
                
                fetch('book_skill.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `skill_id=${encodeURIComponent(skillId)}&preferred_schedule=${encodeURIComponent(schedule)}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'client.php?section=requestSection&success=1';
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Booking failed. Please try again.', 'error');
                        } else {
                            alert(data.message || 'Booking failed');
                        }
                        if (card) {
                            card.style.opacity = "1";
                            card.style.transform = "translateY(0)";
                        }
                    }
                })
                .catch(err => {
                    console.error('Booking error:', err);
                    if (typeof showToast === 'function') {
                        showToast('Network error. Please try again.', 'error');
                    } else {
                        alert('Network error');
                    }
                    if (card) {
                        card.style.opacity = "1";
                        card.style.transform = "translateY(0)";
                    }
                });
            });
        });
    }

    // Load availability data
    async function loadAvailability(providerId, container) {
        try {
            const res = await fetch(`provider.php?ajax=1&action=get_availability&provider=${providerId}`);
            const data = await res.json();
            
            if (data.ok && Array.isArray(data.data) && data.data.length > 0) {
                const availability = data.data;
                const order = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                
                function formatTime12hr(time24) {
                    if (!time24) return '';
                    const [hours, minutes] = time24.split(':').map(Number);
                    const period = hours >= 12 ? 'PM' : 'AM';
                    const hours12 = hours % 12 || 12;
                    return `${hours12}:${minutes.toString().padStart(2, '0')} ${period}`;
                }
                
                const byDay = order.map(day => ({ 
                    day, 
                    slots: availability.filter(s => s.DayOfWeek === day) 
                }));
                
                const working = byDay.filter(d => d.slots.length > 0);
                
                if (working.length > 0) {
                    container.innerHTML = `
                        <div class="availability-display">
                            <div class="availability-header">
                                📅 Available Hours
                            </div>
                            <div class="availability-grid">
                                ${working.map(d => {
                                    const dayShort = d.day.substring(0, 3);
                                    const timeSlots = d.slots.map(s => 
                                        `${formatTime12hr(s.StartTime.substring(0,5))} - ${formatTime12hr(s.EndTime.substring(0,5))}`
                                    ).join('<br>');
                                    return `
                                        <div class="availability-day-slot">
                                            <span class="day-name">${dayShort}</span>
                                            <span class="time-slots">${timeSlots}</span>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                } else {
                    container.innerHTML = `
                        <div class="availability-display">
                            <div style="text-align: center; padding: 20px; color: white;">
                                ⚠️ Availability not provided by provider
                            </div>
                        </div>
                    `;
                }
            } else {
                container.innerHTML = `
                    <div class="availability-display">
                        <div style="text-align: center; padding: 20px; color: white;">
                            ⚠️ Availability not provided by provider
                        </div>
                    </div>
                `;
            }
        } catch (err) {
            console.error('Failed to load availability:', err);
            container.innerHTML = `
                <div class="availability-display">
                    <div style="text-align: center; padding: 20px; color: white;">
                        ❌ Failed to load availability
                    </div>
                </div>
            `;
        }
    }

    // Attach search listeners
    if (searchBox) {
        searchBox.addEventListener('input', debounce(performSearch, 500));
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', performSearch);
    }

    const cityFilter = document.getElementById('cityFilter');
    if (cityFilter) {
        cityFilter.addEventListener('change', performSearch);
    }

    if (rateTypeFilter) {
        rateTypeFilter.addEventListener('change', performSearch);
    }
    
    if (rateRangeFilter) {
        rateRangeFilter.addEventListener('change', performSearch);
    }

    // Clear filters
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchBox) searchBox.value = '';
            if (categoryFilter) categoryFilter.value = 'all';
            if (cityFilter) cityFilter.value = 'all';
            if (rateTypeFilter) rateTypeFilter.value = 'all';
            if (rateRangeFilter) rateRangeFilter.value = 'all';
            performSearch();
        });
    }
});