// ============================================
// BROWSE & SEARCH FUNCTIONALITY
// ============================================

document.addEventListener('DOMContentLoaded', () => {
    // Update price label based on rate type
        const rateTypeFilter = document.getElementById('rateTypeFilter');
        const rateRangeFilter = document.getElementById('rateRangeFilter');

        function updatePriceLabel() {
            const type = rateTypeFilter?.value;
            const options = rateRangeFilter?.options;
            if (!options) return;

            for (let opt of options) {
                if (opt.value === 'all') {
                    opt.text = 'Any Price';
                    continue;
                }
                let text = opt.text.split(' ')[0]; // ₱0
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
        updatePriceLabel(); // on load


    const searchBox = document.getElementById('searchBox');
    const categoryFilter = document.getElementById('categoryFilter');
    const servicesContainer = document.getElementById('servicesContainer');

    // Debounce function to limit API calls
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }//the browse.js

    // Perform search
    async function performSearch() {
        const query = searchBox.value.trim();
        const category = categoryFilter.value;
        const city = document.getElementById('cityFilter')?.value || 'all';
        const rateType = document.getElementById('rateTypeFilter')?.value || 'all';
        const rateRange = document.getElementById('rateRangeFilter')?.value || 'all';

        try {
            // Show loading
            if (servicesContainer) {
                servicesContainer.innerHTML = '<div style="text-align:center; padding:40px;">🔍 Searching...</div>';
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

    // Render search results
    function renderResults(skills) {
        if (!servicesContainer) return;

        if (skills.length === 0) {
            servicesContainer.innerHTML = '<div class="empty-state">No services found matching your criteria.</div>';
            return;
        }

        servicesContainer.innerHTML = skills.map(p => {
            // Format location
            const locationParts = [p.Barangay, p.City, p.Province].filter(Boolean);
            const location = locationParts.length > 0 
                ? locationParts.join(', ') 
                : (p.Location || 'Unknown');

            // Truncate description
            const description = p.Description || '';
            const shortDesc = description.length > 100 
                ? description.substring(0, 100) + '...' 
                : description;

            return `
                    <div class="provider-card"
                        data-city="${escapeHtml(p.City || '')}"
                        data-province="${escapeHtml(p.Province || '')}"
                        data-barangay="${escapeHtml(p.Barangay || '')}">

                        <div class="provider-header">
                            <h3>${escapeHtml((p.FName || '') + ' ' + (p.LName || ''))}</h3>
                            <span class="category-badge">${escapeHtml(p.SkillName || 'Other')}</span>
                        </div>
                        
                        <div class="provider-details">
                            <p><strong>Location:</strong> ${escapeHtml(location)}</p>
                            <p class="rate-highlight"><strong>Rate:</strong> PHP ${parseFloat(p.Rate || 0).toFixed(2)}
                                ${p.RateType === 'daily' ? '/day' : (p.RateType === 'fixed' ? ' (fixed)' : '/hour')}
                            </p>
                        </div>

                        <p class="card-description">${escapeHtml(shortDesc)}</p>

                        <div class="card-actions">
                            <button class="btn-secondary read-more-btn" 
                                    data-description="${escapeHtml(description)}">
                                Read More
                            </button>
                            <form method="POST" class="book-form" style="display:inline;">
                                <input type="hidden" name="book_skill_id" value="${parseInt(p.SkillID) || 0}">
                                <input type="hidden" name="provider_id" value="${parseInt(p.UserID) || 0}">
                                <input type="text" class="flatpickr-input" placeholder="Pick date & time" required readonly>
                                <input type="hidden" name="preferred_schedule" value="">
                                <button type="submit" class="btn-primary book-btn">Book Now</button>
                            </form>
                        </div>
                    </div>
                `;
        }).join('');

        // Re-attach event listeners
        reattachEventListeners();
        
        // ⚠️ CRITICAL: Re-initialize flatpickr for dynamically loaded forms
        setTimeout(() => initializeDatePickers(), 100);
    }

        

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Re-attach event listeners after rendering
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

        // ⚠️ Book forms now handled by event delegation in client.js
        // The AJAX handler will pick up these forms automatically
        document.querySelectorAll('.book-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault(); // Prevent default, let AJAX handler take over
                
                const hidden = form.querySelector('input[name="preferred_schedule"]');
                const input = form.querySelector('.flatpickr-input');
                
                // Validate schedule
                if (!hidden || !hidden.value) {
                    if (input) input.focus();
                    let hint = form.querySelector('.schedule-hint');
                    if (!hint) {
                        hint = document.createElement('div');
                        hint.className = 'schedule-hint error';
                        if (input && input.parentElement) input.parentElement.appendChild(hint);
                        else form.appendChild(hint);
                    }
                    hint.classList.add('error');
                    hint.style.color = '#991b1b';
                    hint.textContent = 'Please set a date and time before booking.';
                    return;
                }
                
                // Apply animation
                const card = this.closest(".provider-card");
                if (card) {
                    card.style.transition = "opacity 0.4s ease, transform 0.4s ease";
                    card.style.opacity = "0.6";
                    card.style.transform = "translateY(4px)";
                }
                
                // Get form data
                const formData = new FormData(this);
                const skillId = formData.get('book_skill_id');
                const schedule = formData.get('preferred_schedule');
                
                // Submit via AJAX
                fetch('book_skill.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `skill_id=${encodeURIComponent(skillId)}&preferred_schedule=${encodeURIComponent(schedule)}`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'client.php?section=requestSection&success=1';
                    } else if (data.error === 'already_booked') {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'You already have an active booking for this service.', 'error');
                        } else {
                            alert(data.message);
                        }
                        if (card) {
                            card.style.opacity = "1";
                            card.style.transform = "translateY(0)";
                        }
                    } else {
                        if (typeof showToast === 'function') {
                            showToast(data.message || 'Booking failed. Please try again.', 'error');
                        } else {
                            alert(data.message);
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
                        alert('Network error. Please try again.');
                    }
                    if (card) {
                        card.style.opacity = "1";
                        card.style.transform = "translateY(0)";
                    }
                });
            });
        });
    }

    // Attach search listeners
    if (searchBox) {
        searchBox.addEventListener('input', debounce(performSearch, 500));
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', performSearch);
    }

    // City filter
    const cityFilter = document.getElementById('cityFilter');
    if (cityFilter) {
        cityFilter.addEventListener('change', performSearch);
    }

       // Rate Type & Range filters
    if (rateTypeFilter) {
        rateTypeFilter.addEventListener('change', performSearch);
    }
    if (rateRangeFilter) {
        rateRangeFilter.addEventListener('change', performSearch);
    }

    // Clear filters button
    const clearFiltersBtn = document.getElementById('clearFilters');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', () => {
            if (searchBox) searchBox.value = '';
            if (categoryFilter) categoryFilter.value = 'all';
            if (cityFilter) cityFilter.value = 'all';
            const rateTypeFilter = document.getElementById('rateTypeFilter');
            const rateRangeFilter = document.getElementById('rateRangeFilter');
            if (rateTypeFilter) rateTypeFilter.value = 'all';
            if (rateRangeFilter) rateRangeFilter.value = 'all';
            performSearch();
        });
    }
});