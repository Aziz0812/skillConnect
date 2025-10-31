document.addEventListener('DOMContentLoaded', () => {
  console.log("✅ DOM fully loaded — checking skill_added...");

  //  Force Skills Section BEFORE section logic runs
  if (window.location.search.includes('skill_added=1')) {
      console.log("🎯 Skill added redirect — forcing skills section");
      showSection(skillsSection);
      localStorage.setItem('activeSection', skillsSection.id);
      window.location.hash = '#skills-section';

      //  Clean URL so it won’t repeat next load
      const cleanUrl = window.location.pathname + "#skills-section";
      window.history.replaceState({}, '', cleanUrl);

      return; //  Prevent dashboard fallback logic from running
  }
  
  if (window.location.search) {
  window.history.replaceState({}, document.title, window.location.pathname + "#skills-section");
}

  //  Detect if redirected after adding a skill
if (window.location.search.includes('skill_added=1')) {
  document.dispatchEvent(new Event('skill-added'));

  //  Clean the URL after handling
  const cleanUrl = window.location.pathname + "#skills-section";
  window.history.replaceState({}, '', cleanUrl);
}

  console.log('provider.js loaded âœ…');

  // ----------------------------
  // NAVIGATION & SECTION HANDLING
  // ----------------------------
  const dashboardLink = document.getElementById('dashboard');
  const postServiceLink = document.getElementById('postServiceLink');
  const skillsLink = document.getElementById('skillsLink');
  const jobsLink = document.getElementById('jobsLink');
  const addSkillFirst = document.getElementById('addSkillFirst');

  const dashboardSection = document.getElementById('dashboard-section');
  const postServiceSection = document.getElementById('add-skill');
  const skillsSection = document.getElementById('skills-section');
  const jobsSection = document.getElementById('jobs-section');
    // --- JOB TAB SWITCHER (animated + styled) ---
    const jobTabs = document.querySelectorAll('#jobs-section [data-tab]');
    const jobSections = document.querySelectorAll('#jobs-section .request-section');

    jobTabs.forEach(btn => {
      btn.addEventListener('click', () => {
        // reset all buttons
        jobTabs.forEach(b => b.classList.remove('active', 'btn-primary'));
        btn.classList.add('active', 'btn-primary');

        // fade out all sections
        jobSections.forEach(sec => {
          sec.style.opacity = 0;
          setTimeout(() => sec.style.display = 'none', 200);
        });

        // show the selected one
        const targetId = `requestSection-${btn.dataset.tab}`;
        const target = document.getElementById(targetId);
        if (target) {
          setTimeout(() => {
            target.style.display = 'block';
            target.style.opacity = 1;
          }, 200);
        }
      });
    });
    

    // initial state
    jobSections.forEach(sec => {
      sec.style.display = sec.id === 'requestSection-active' ? 'block' : 'none';
      sec.style.opacity = sec.id === 'requestSection-active' ? 1 : 0;
    });
   
    


  const allSections = [dashboardSection, postServiceSection, skillsSection, jobsSection];
  
  // --- HASH / REDIRECT HANDLER (ensures correct section after PHP redirect) ---
    function showSectionByHash() {
      const hash = window.location.hash || '#dashboard-section';

      const sections = {
        '#dashboard-section': dashboardSection,
        '#add-skill': postServiceSection,
        '#skills-section': skillsSection,
        '#jobs-section': jobsSection
      };

      // Hide all first
      allSections.forEach(sec => {
        if (sec) {
          sec.style.display = 'none';
          sec.classList.remove('active');
        }
      });

      // Reset nav states
      document.querySelectorAll('.navbar-nav .nav-link')
        .forEach(link => link.classList.remove('active'));

      // Show matched section (fallback to dashboard if unknown)
      const target = sections[hash] || dashboardSection;
      if (target) {
        target.style.display = 'block';
        target.classList.add('active');

        // Activate proper nav link
        const activeLink = document.querySelector(`a[href="${hash}"]`);
        if (activeLink) activeLink.classList.add('active');

        // Ensure we don't jump unexpectedly if already visible
        if (document.visibilityState === 'visible') {
          target.scrollIntoView({ behavior: 'auto' });
        }
      }
    }

// Run once on load to respect any server-side redirect like provider.php?job_updated=1#jobs-section
showSectionByHash();

// Also handle manual / browser hash changes
window.addEventListener('hashchange', showSectionByHash);


  const categorySelect = document.getElementById('category');
  const otherCategoryGroup = document.getElementById('otherCategoryGroup');
  const otherCategoryInput = document.getElementById('otherCategory');
  const rateInput = document.getElementById('rate');

        // --- LIVE PREVIEW (category, description, rate, image) ---
      const previewCategory = document.getElementById('previewCategory');
      const previewDescription = document.getElementById('previewDescription');
      const previewRate = document.getElementById('previewRate');
      const previewImage = document.getElementById('previewImage');

      // Category live update (handles normal categories + "Other (Specify)" typed value)
      if (categorySelect && previewCategory) {
        // otherCategoryInput is already defined earlier in file; if not, get it here safely
        const otherCategoryEl = otherCategoryInput || document.getElementById('otherCategory');

        function updateCategoryPreview() {
          if (categorySelect.value === 'others') {
            const custom = otherCategoryEl?.value?.trim();
            previewCategory.textContent = custom && custom.length ? custom : 'Other (Specify)';
          } else {
            previewCategory.textContent = categorySelect.options[categorySelect.selectedIndex]?.text || '—';
          }
        }

        // Update when dropdown changes
        categorySelect.addEventListener('change', updateCategoryPreview);

       // Clean and format "Other Category" input
        if (otherCategoryEl) {
          otherCategoryEl.addEventListener('input', e => {
            // Remove numbers and special characters
            let value = e.target.value.replace(/[^A-Za-z\s]/g, '');

            // Capitalize each word (e.g., home repair → Home Repair)
            value = value
              .toLowerCase()
              .replace(/\b\w/g, char => char.toUpperCase());

            e.target.value = value;

            updateCategoryPreview(); // update live preview
          });
        }

        // Initialize preview on load
        updateCategoryPreview();
      }


      // Description live update
      const descInput = document.getElementById('description');
      descInput?.addEventListener('input', e => {
        previewDescription.textContent = e.target.value || '—';
        const counter = document.getElementById('descCounter');
        if (counter) counter.textContent = `${e.target.value.length}/500 characters`;
      });

  


      // Image live preview
      const imageInput = document.getElementById('serviceImage');
      imageInput?.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = ev => {
            previewImage.innerHTML =
              `<img src="${ev.target.result}" alt="Preview"
                    class="img-fluid rounded shadow-sm mt-2" style="max-height:200px;">`;
          };
          reader.readAsDataURL(file);
        } else {
          previewImage.textContent = 'No image selected';
        }
      });

  const ratePreview = document.getElementById('ratePreview');

  // --- CATEGORY: Show/Hide Other Field (works for add + edit forms) ---
    function toggleOtherField(select) {
      const form = select.closest('form');
      if (!form) return;

      const otherGroup = form.querySelector('#otherCategoryGroup, .otherCategoryGroup');
      const otherInput = form.querySelector('#otherCategory, .otherCategoryInput');
      if (!otherGroup || !otherInput) return;

      if (select.value === 'others') {
        otherGroup.style.display = 'block';
        // Only require if empty (so editing existing custom skill won’t block updates)
        if (!otherInput.value.trim()) {
          otherInput.required = true;
          otherInput.focus();
        } else {
          otherInput.required = false;
        }
      } else {
        otherGroup.style.display = 'none';
        otherInput.required = false;
        otherInput.value = '';
      }

    }



    // Listen for changes on all category selects (add + edit)
    document.addEventListener('change', e => {
      if (e.target.matches('select[name="category_id"], #category')) {
        toggleOtherField(e.target);
      }
    });

    // --- APPLY CLEANING FOR ALL "Other (Specify)" INPUTS (add + edit) ---
      document.addEventListener('input', e => {
        if (e.target.matches('#otherCategory, .otherCategoryInput')) {
          let value = e.target.value.replace(/[^A-Za-z\s]/g, '');
          value = value.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
          e.target.value = value;
        }
      });


    // Initialize correct visibility on page load
    document.querySelectorAll('select[name="category_id"], #category').forEach(sel => {
      toggleOtherField(sel);
    });

    

      // --- RATE PREVIEW (with rate type) ---
    const rateTypeRadios = document.querySelectorAll('input[name="rate_type"]');

    if (rateInput && ratePreview && rateTypeRadios.length > 0) {
            const updateRatePreview = () => {
        const rate = parseFloat(rateInput.value);
        const selectedType = document.querySelector('input[name="rate_type"]:checked')?.value || 'hourly';
        let unitLabel = '';

        switch (selectedType) {
          case 'daily': unitLabel = '/day'; break;
          case 'fixed': unitLabel = ' (total)'; break;
          default: unitLabel = '/hour';
        }

        // --- Update both small text and live preview badge ---
        if (isNaN(rate) || rate <= 0) {
          ratePreview.textContent = 'Please enter a valid positive rate.';
          previewRate.textContent = '₱0/hr';
          ratePreview.style.color = 'red';
          previewRate.style.color = 'gray';
        } else {
          const display = `₱${rate.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${unitLabel}`;
          ratePreview.textContent = display;
          previewRate.textContent = display;
          ratePreview.style.color = 'green';
          previewRate.style.color = 'green';
        }
      };


      rateInput.addEventListener('input', updateRatePreview);
      rateTypeRadios.forEach(radio => radio.addEventListener('change', updateRatePreview));

      ratePreview.textContent = 'Enter rate above';
    }


  // --- AUTO-HIDE ALERT MESSAGES ---
  document.querySelectorAll('.success-message, .error-message').forEach(msg => {
    setTimeout(() => {
      msg.classList.add('fade-out');
      setTimeout(() => msg.remove(), 600);
    }, 3000);
  });

  // --- SHOW ACTIVE SECTION ---
  function showSection(section) {
    allSections.forEach(sec => {
      if (sec) {
        sec.classList.remove('active');
        sec.style.display = 'none';
      }
    });

    if (section) {
      section.style.display = 'block';
      section.classList.add('active');

      // âœ… Delay dashboard data load slightly so DOM is ready
      if (section === dashboardSection) {
        console.log('Dashboard activated - loading availability and charts');
        setTimeout(() => {
          loadAvailability();
          loadCharts();
        }, 200);
      }
    }

    document.querySelectorAll('.nav-links a').forEach(link => link.classList.remove('active'));
    if (section === dashboardSection) dashboardLink?.classList.add('active');
    if (section === postServiceSection) postServiceLink?.classList.add('active');
    if (section === skillsSection) skillsLink?.classList.add('active');
    if (section === jobsSection) jobsLink?.classList.add('active');

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // --- HELPER: NAV CLICK HANDLER ---
  function handleNavClick(e, section, hash) {
    e.preventDefault();
    showSection(section);
    location.hash = hash;
    localStorage.setItem('activeSection', section.id); // âœ… remember section
  }

  // --- NAV EVENTS ---
  if (dashboardLink) dashboardLink.addEventListener('click', e => handleNavClick(e, dashboardSection, '#dashboard-section'));
  if (postServiceLink) postServiceLink.addEventListener('click', e => handleNavClick(e, postServiceSection, '#add-skill'));
  if (skillsLink) skillsLink.addEventListener('click', e => handleNavClick(e, skillsSection, '#skills-section'));
  if (jobsLink) jobsLink.addEventListener('click', e => handleNavClick(e, jobsSection, '#jobs-section'));
  if (addSkillFirst) addSkillFirst.addEventListener('click', e => handleNavClick(e, postServiceSection, '#add-skill'));
      // After adding a skill → redirect to My Skills to view
      document.addEventListener('skill-added', () => {
        showSection(skillsSection);
        location.hash = '#skills-section';
        localStorage.setItem('activeSection', skillsSection.id);
      });

      // ✅ If just redirected after adding a skill, set correct section BEFORE init runs
      if (window.location.search.includes('skill_added=1')) {

        // Force correct UI state first
        localStorage.setItem('activeSection', 'skills-section');
        window.location.hash = '#skills-section';

        // ✅ Optional: clean URL on next load
        setTimeout(() => {
          const cleanUrl = window.location.pathname + "#skills-section";
          window.history.replaceState({}, '', cleanUrl);
        }, 500);
      }
if (window.location.search.includes('updated=1') ||
    window.location.search.includes('deleted=1')) {
    showSection(skillsSection);
    localStorage.setItem('activeSection', skillsSection.id);
    window.location.hash = '#skills-section';
}


  // --- DEFAULT SECTION / HASH RESTORE (final fixed version) ---
  (function initSectionFromHashOrStorage() {
    const hash = window.location.hash;

    // 1️⃣ If a hash exists (like #jobs-section), show that section
    if (hash) {
      const target = document.querySelector(hash);
      if (target) {
        showSection(target);
        localStorage.setItem('activeSection', target.id);
        console.log("✅ Loaded section from hash:", hash);
        return;
      }
    }

    // 2️⃣ Otherwise, restore from localStorage or default to dashboard
    const savedId = localStorage.getItem('activeSection');
    const saved = savedId ? document.getElementById(savedId) : null;

    if (saved) {
      showSection(saved);
      console.log("ℹ️ Restored section from storage:", savedId);
    } else if (dashboardSection) {
      showSection(dashboardSection);
      console.log("➡️ Defaulted to dashboard");
    }
  })();

  // 🔁 Handle browser navigation and redirects correctly
  window.addEventListener('hashchange', () => {
    const hash = window.location.hash;
    const target = hash ? document.querySelector(hash) : null;

    if (target) {
      showSection(target);
      localStorage.setItem('activeSection', target.id);
      console.log("🔁 Hash changed to:", hash);
    }
  });



  // ======================================================
  //                AVAILABILITY MANAGEMENT
  // ======================================================
  const base = 'provider.php?ajax=1';
  const fetchJSON = async (url, opts = {}) => {
    try {
      const res = await fetch(url, opts);
      return await res.json();
    } catch (e) {
      console.error('Fetch error:', e);
      return { ok: false };
    }
  };

  // --- Convert "HH:MM:SS" or "HH:MM" to 12-hour format ---
  function formatTime12(timeStr) {
    if (!timeStr) return '';
    const [hourStr, minuteStr] = timeStr.split(':');
    let hour = parseInt(hourStr, 10);
    const minute = minuteStr.padStart(2, '0');
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${minute} ${ampm}`;
  }

  const form = document.getElementById('availabilityForm');
  const list = document.getElementById('availabilityList');

        async function loadAvailability() {
        console.log('Loading availability...');
        const res = await fetchJSON(`${base}&action=list_availability`);
        const container = list;

        if (!res.ok) {
          container.innerHTML = `<div class="text-danger text-center">âš  Failed to load availability.</div>`;
          return;
        }

        const availabilities = res.data || [];
        if (availabilities.length === 0) {
          container.innerHTML = `
            <div class="text-muted text-center py-3">
              <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
              No availability set yet.<br>
              <small>Add your working days and hours above.</small>
            </div>`;
          return;
        }

        const dayColors = {
          Monday: '#0d6efd',
          Tuesday: '#6610f2',
          Wednesday: '#6f42c1',
          Thursday: '#198754',
          Friday: '#20c997',
          Saturday: '#fd7e14',
          Sunday: '#dc3545'
        };

        const rows = availabilities.map(a => `
          <tr class="align-middle">
            <td>
              <span class="badge text-light" style="background-color: ${dayColors[a.DayOfWeek] || '#6c757d'};">
                ${a.DayOfWeek}
              </span>
            </td>
            <td>
              <span class="badge bg-light text-dark border px-3 py-2">
                ${formatTime12(a.StartTime)}
              </span>
            </td>
            <td>
              <span class="badge bg-light text-dark border px-3 py-2">
                ${formatTime12(a.EndTime)}
              </span>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-danger delete-avail" data-id="${a.AvailabilityID}">
                <i class="bi bi-trash"></i>
              </button>
            </td>
          </tr>
        `).join('');

        container.innerHTML = `
          <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
              <h6 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Current Schedule</h6>
              <small>${availabilities.length} day${availabilities.length > 1 ? 's' : ''}</small>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0 text-center align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Day</th>
                      <th>Start</th>
                      <th>End</th>
                      <th>Action</th>
                    </tr>
                  </thead>
                  <tbody>${rows}</tbody>
                </table>
              </div>
            </div>
          </div>
        `;

  // Delete handlers
  document.querySelectorAll('.delete-avail').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Remove this schedule?')) return;
      const fd = new FormData();
      fd.append('id', btn.dataset.id);
      const del = await fetchJSON(`${base}&action=delete_availability`, { method: 'POST', body: fd });
      if (del.ok) loadAvailability();
    });
  });
}


  if (form) {
    form.addEventListener('submit', async e => {
      e.preventDefault();

      const start = form.querySelector('#availStart').value;
      const end = form.querySelector('#availEnd').value;
      const checked = [...form.querySelectorAll('input[name="days[]"]:checked')].map(c => c.value);

      if (!start || !end) {
        alert('Please select both start and end times.');
        return;
      }

      const startTime = new Date(`1970-01-01T${start}:00`);
      const endTime = new Date(`1970-01-01T${end}:00`);
      if (endTime <= startTime) {
        alert('End time must be later than start time.');
        return;
      }

      if (checked.length === 0) {
        alert('Please select at least one day.');
        return;
      }

      let addedAny = false;
      for (const day of checked) {
        const fd = new FormData();
        fd.append('day', day);
        fd.append('start_time', start);
        fd.append('end_time', end);
        const res = await fetchJSON(`${base}&action=add_availability`, { method: 'POST', body: fd });

        if (res.ok) addedAny = true;
        else if (res.error) alert(`${day}: ${res.error}`);
      }

      if (addedAny) {
        form.reset();
        loadAvailability();
      }
    });
  }

  // ======================================================
  //                CHART LOADING
  // ======================================================
  async function loadCharts() {
    console.log('Loading charts...');

    // Fetch Requests Over Time data
    const timeRes = await fetchJSON(`${base}&action=requests_over_time`);
    console.log('Requests Over Time response:', timeRes);
    const timeCtx = document.getElementById('requestsOverTimeChart')?.getContext('2d');
    console.log('Time context:', timeCtx);

    if (timeCtx && timeRes.ok && timeRes.data) {
      const labels = timeRes.data.map(item => item.month || 'Unknown');
      const counts = timeRes.data.map(item => item.count);

      if (labels.length === 0) {
        timeCtx.canvas.parentElement.innerHTML += '<div class="text-muted text-center mt-2">No request data over time.</div>';
        return;
      }

      new Chart(timeCtx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Requests Over Time',
            data: counts,
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            fill: true,
            tension: 0.4
          }]
        },
        options: {
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: 'Number of Requests' }
            },
            x: {
              title: { display: true, text: 'Month' }
            }
          }
        }
      });
    } else {
      console.error('Failed to load Requests Over Time chart:', timeRes);
      if (timeCtx) {
        timeCtx.canvas.parentElement.innerHTML += '<div class="text-danger text-center mt-2">Failed to load chart data.</div>';
      }
    }

    // Fetch Status Summary data
    const statusRes = await fetchJSON(`${base}&action=status_summary`);
    console.log('Status Summary response:', statusRes);
    const statusCtx = document.getElementById('statusSummaryChart')?.getContext('2d');

    if (statusCtx && statusRes.ok && statusRes.data) {
      const statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
      const counts = statuses.map(status => statusRes.data[status] || 0);

      const total = counts.reduce((a, b) => a + b, 0);
      if (total === 0) {
        statusCtx.canvas.parentElement.innerHTML += '<div class="text-muted text-center mt-2">No request status data.</div>';
        return;
      }

      new Chart(statusCtx, {
        type: 'pie',
        data: {
          labels: statuses,
          datasets: [{
            label: 'Request Status',
            data: counts,
            backgroundColor: ['#ffc107', '#17a2b8', '#007bff', '#28a745', '#dc3545'],
            borderColor: ['#e0a800', '#138496', '#0056b3', '#1e7e34', '#b02a37'],
            borderWidth: 1
          }]
        },
        options: {
          plugins: {
            legend: { position: 'top' }
          }
        }
      });
    } else {
      console.error('Failed to load Status Summary chart:', statusRes);
      if (statusCtx) {
        statusCtx.canvas.parentElement.innerHTML += '<div class="text-danger text-center mt-2">Failed to load chart data.</div>';
      }
    }
   
      // ======================================================
      //     LOAD TOP 3 MOST BOOKED SKILLS WIDGET
      // ======================================================
      const topSkillsContainer = document.getElementById('topSkillsContainer');
      if (topSkillsContainer) {
        const topRes = await fetchJSON(`${base}&action=top_skills`);
        console.log('Top Skills:', topRes);

        if (topRes.ok && topRes.data.length > 0) {
          const listHTML = topRes.data.map(
            (s, i) => `
              <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                <span><strong>${i + 1}.</strong> ${s.SkillName}</span>
                <span class="badge bg-primary">${s.TotalBookings} Bookings</span>
              </div>`
          ).join('');

          topSkillsContainer.innerHTML = `<div>${listHTML}</div>`;
        } else {
          topSkillsContainer.innerHTML = `
            <div class="text-muted text-center">No bookings yet.</div>
          `;
        }
      }


  }
// ======================================================
//            SEARCH & FILTER LOGIC (GLOBAL)
// ======================================================
const skillsFilterForm = document.getElementById('skillsFilterForm');
const skillsSearchInput = document.getElementById('searchSkill');
const filterCategorySelect = document.getElementById('filterCategory');
const clearFiltersBtn = document.getElementById('clearFilters');

// 1) Clear search text whenever a category is chosen
if (filterCategorySelect && skillsSearchInput) {
  filterCategorySelect.addEventListener('change', () => {
    skillsSearchInput.value = '';
  });
}

// 2) Clicking "My Skills" → reset filters and clean URL
  if (skillsLink) {
    skillsLink.addEventListener('click', e => {
      e.preventDefault();
      showSection(skillsSection);
      location.hash = '#skills-section';
      localStorage.setItem('activeSection', skillsSection.id);
    });
  }


// 3) Clear button: reset form fields AND reload cleanly
if (clearFiltersBtn) {
  clearFiltersBtn.addEventListener('click', (e) => {
    e.preventDefault();
    console.log('[Clear] clicked');

    if (skillsFilterForm) skillsFilterForm.reset();
    if (skillsSearchInput) skillsSearchInput.value = '';
    if (filterCategorySelect) filterCategorySelect.value = '';

    // ✅ Force clean PHP reload
    window.location.href = window.location.pathname;
  });
}

document.querySelectorAll('.toast').forEach(toastEl => {
        new bootstrap.Toast(toastEl, { delay: 4000 }).show();
    });

  // ===================
  // TOAST HANDLER
  // ===================
  function showToast(message, type = 'success') {
      const toastArea = document.getElementById('toast-area');
      if (!toastArea) return;

      const toast = document.createElement('div');
      toast.className = `toast align-items-center text-bg-${type} border-0 show mb-2`;
      toast.role = "alert";
      toast.innerHTML = `
          <div class="d-flex">
              <div class="toast-body">${message}</div>
              <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                  data-bs-dismiss="toast"></button>
          </div>
      `;

      toastArea.appendChild(toast);

      const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
      bsToast.show();
  }

  // ✅ Automatically show messages passed from PHP
  if (window.success_message) showToast(window.success_message, 'success');
  if (window.error_message) showToast(window.error_message, 'danger');

});