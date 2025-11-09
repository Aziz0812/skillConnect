// ============================================
// PROVIDER.JS - COMPLETE FIXED VERSION
// ============================================

document.addEventListener('DOMContentLoaded', () => {
  console.log("✅ DOM fully loaded");

  // ✅ Handle all skill-related redirects in ONE place
  if (window.location.search.includes('skill_added=1') || 
    window.location.search.includes('updated=1') ||
    window.location.search.includes('deleted=1')) {
    
    const cleanUrl = window.location.pathname + "#skills-section";
    window.history.replaceState({}, '', cleanUrl);
    localStorage.setItem('activeSection', 'skills-section');
  }
  
  // Handle job status updates
  if (window.location.search.includes('job_updated=1')) {
    const hash = window.location.hash || '#jobs-section';
    const cleanUrl = window.location.pathname + hash;
    window.history.replaceState({}, '', cleanUrl);
    localStorage.setItem('activeSection', 'jobs-section');
  }

  console.log('provider.js loaded ✅');

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
      jobTabs.forEach(b => b.classList.remove('active', 'btn-primary'));
      btn.classList.add('active', 'btn-primary');

      jobSections.forEach(sec => {
        sec.style.opacity = 0;
        setTimeout(() => sec.style.display = 'none', 200);
      });

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
  
  // --- HASH / REDIRECT HANDLER ---
  function showSectionByHash() {
    const hash = window.location.hash || '#dashboard-section';

    const sections = {
      '#dashboard-section': dashboardSection,
      '#add-skill': postServiceSection,
      '#skills-section': skillsSection,
      '#jobs-section': jobsSection
    };

    allSections.forEach(sec => {
      if (sec) {
        sec.style.display = 'none';
        sec.classList.remove('active');
      }
    });

    document.querySelectorAll('.navbar-nav .nav-link')
      .forEach(link => link.classList.remove('active'));

    const target = sections[hash] || dashboardSection;
    if (target) {
      target.style.display = 'block';
      target.classList.add('active');

      const activeLink = document.querySelector(`a[href="${hash}"]`);
      if (activeLink) activeLink.classList.add('active');

      if (document.visibilityState === 'visible') {
        target.scrollIntoView({ behavior: 'auto' });
      }
    }
  }

  showSectionByHash();
  window.addEventListener('hashchange', showSectionByHash);

  const categorySelect = document.getElementById('category');
  const otherCategoryGroup = document.getElementById('otherCategoryGroup');
  const otherCategoryInput = document.getElementById('otherCategory');
  const rateInput = document.getElementById('rate');

  // --- LIVE PREVIEW ---
  const previewCategory = document.getElementById('previewCategory');
  const previewDescription = document.getElementById('previewDescription');
  const previewRate = document.getElementById('previewRate');
  const previewImage = document.getElementById('previewImage');

  if (categorySelect && previewCategory) {
    const otherCategoryEl = otherCategoryInput || document.getElementById('otherCategory');

    function updateCategoryPreview() {
      if (categorySelect.value === 'others') {
        const custom = otherCategoryEl?.value?.trim();
        previewCategory.textContent = custom && custom.length ? custom : 'Other (Specify)';
      } else {
        previewCategory.textContent = categorySelect.options[categorySelect.selectedIndex]?.text || '—';
      }
    }

    categorySelect.addEventListener('change', updateCategoryPreview);

    if (otherCategoryEl) {
      otherCategoryEl.addEventListener('input', e => {
        let value = e.target.value.replace(/[^A-Za-z\s]/g, '');
        value = value.toLowerCase().replace(/\b\w/g, char => char.toUpperCase());
        e.target.value = value;
        updateCategoryPreview();
      });
    }

    updateCategoryPreview();
  }

  const descInput = document.getElementById('description');
  descInput?.addEventListener('input', e => {
    previewDescription.textContent = e.target.value || '—';
    const counter = document.getElementById('descCounter');
    if (counter) counter.textContent = `${e.target.value.length}/500 characters`;
  });

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

  // --- CATEGORY: Show/Hide Other Field ---
  function toggleOtherField(select) {
    const form = select.closest('form');
    if (!form) return;

    const otherGroup = form.querySelector('#otherCategoryGroup, .otherCategoryGroup');
    const otherInput = form.querySelector('#otherCategory, .otherCategoryInput');
    if (!otherGroup || !otherInput) return;

    if (select.value === 'others') {
      otherGroup.style.display = 'block';
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

  document.addEventListener('change', e => {
    if (e.target.matches('select[name="category_id"], #category')) {
      toggleOtherField(e.target);
    }
  });

  document.addEventListener('input', e => {
    if (e.target.matches('#otherCategory, .otherCategoryInput')) {
      let value = e.target.value.replace(/[^A-Za-z\s]/g, '');
      value = value.toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
      e.target.value = value;
    }
  });

  document.querySelectorAll('select[name="category_id"], #category').forEach(sel => {
    toggleOtherField(sel);
  });

  // --- RATE PREVIEW ---
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

  // --- NAV CLICK HANDLER ---
  function handleNavClick(e, section, hash) {
    e.preventDefault();
    showSection(section);
    location.hash = hash;
    localStorage.setItem('activeSection', section.id);
  }

  // --- NAV EVENTS ---
  if (dashboardLink) dashboardLink.addEventListener('click', e => handleNavClick(e, dashboardSection, '#dashboard-section'));
  if (postServiceLink) postServiceLink.addEventListener('click', e => handleNavClick(e, postServiceSection, '#add-skill'));
  if (skillsLink) skillsLink.addEventListener('click', e => handleNavClick(e, skillsSection, '#skills-section'));
  if (jobsLink) jobsLink.addEventListener('click', e => handleNavClick(e, jobsSection, '#jobs-section'));
  if (addSkillFirst) addSkillFirst.addEventListener('click', e => handleNavClick(e, postServiceSection, '#add-skill'));

  // --- DEFAULT SECTION / HASH RESTORE ---
  (function initSectionFromHashOrStorage() {
    const hash = window.location.hash;

    if (hash) {
      const target = document.querySelector(hash);
      if (target) {
        showSection(target);
        localStorage.setItem('activeSection', target.id);
        console.log("✅ Loaded section from hash:", hash);
        return;
      }
    }

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

  // --- Convert 12-hour format to 24-hour format ---
  function convertTo24Hour(time12, period) {
    let [hours, minutes] = time12.split(':').map(Number);
    
    if (period === 'PM' && hours !== 12) {
      hours += 12;
    } else if (period === 'AM' && hours === 12) {
      hours = 0;
    }
    
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
  }

  const form = document.getElementById('availabilityForm');
  const list = document.getElementById('availabilityList');

  async function loadAvailability() {
    console.log('Loading availability...');
    const res = await fetchJSON(`${base}&action=list_availability`);
    const container = list;

    if (!res.ok) {
      container.innerHTML = `<div class="text-danger text-center">⚠ Failed to load availability.</div>`;
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

      const startHour = form.querySelector('#availStartHour').value;
      const startMin = form.querySelector('#availStartMin').value;
      const startPeriod = form.querySelector('#startPeriod').value;

      const endHour = form.querySelector('#availEndHour').value;
      const endMin = form.querySelector('#availEndMin').value;
      const endPeriod = form.querySelector('#endPeriod').value;

      const checked = [...form.querySelectorAll('input[name="days[]"]:checked')].map(c => c.value);

      if (!startHour || !startMin || !endHour || !endMin) {
        alert('Please select all time fields (hour and minute).');
        return;
      }

      const start12 = `${startHour}:${startMin}`;
      const end12 = `${endHour}:${endMin}`;

      const start24 = convertTo24Hour(start12, startPeriod);
      const end24 = convertTo24Hour(end12, endPeriod);

      const startTime = new Date(`1970-01-01T${start24}:00`);
      const endTime = new Date(`1970-01-01T${end24}:00`);
      
      if (endTime <= startTime) {
        alert(`End time must be later than start time.\n\nYou selected:\nStart: ${start12} ${startPeriod}\nEnd: ${end12} ${endPeriod}`);
        return;
      }

      if (checked.length === 0) {
        alert('Please select at least one day.');
        return;
      }

      let addedAny = false;
      let errorMessages = [];

      for (const day of checked) {
        const fd = new FormData();
        fd.append('day', day);
        fd.append('start_time', start24);
        fd.append('end_time', end24);
        const res = await fetchJSON(`${base}&action=add_availability`, { method: 'POST', body: fd });

        if (res.ok) {
          addedAny = true;
        } else if (res.error) {
          errorMessages.push(`${day}: ${res.error}`);
        }
      }

      if (errorMessages.length > 0) {
        alert(errorMessages.join('\n'));
      }

      if (addedAny) {
        form.querySelector('#availStartHour').value = '08';
        form.querySelector('#availStartMin').value = '00';
        form.querySelector('#startPeriod').value = 'AM';
        form.querySelector('#availEndHour').value = '05';
        form.querySelector('#availEndMin').value = '00';
        form.querySelector('#endPeriod').value = 'PM';
        form.querySelectorAll('input[name="days[]"]').forEach(cb => cb.checked = false);
        
        loadAvailability();
      }
    });
  }

  // ======================================================
  //                CHART LOADING
  // ======================================================
  async function loadCharts() {
    console.log('Loading charts...');

    if (window.requestsTimeChart) {
      window.requestsTimeChart.destroy();
      window.requestsTimeChart = null;
    }
    if (window.statusChart) {
      window.statusChart.destroy();
      window.statusChart = null;
    }

    const timeRes = await fetchJSON(`${base}&action=requests_over_time`);
    const timeCtx = document.getElementById('requestsOverTimeChart')?.getContext('2d');

    if (timeCtx && timeRes.ok && timeRes.data) {
      const labels = timeRes.data.map(item => item.month || 'Unknown');
      const counts = timeRes.data.map(item => item.count);

      if (labels.length === 0) {
        timeCtx.canvas.parentElement.innerHTML += '<div class="text-muted text-center mt-2">No request data over time.</div>';
      } else {
        window.requestsTimeChart = new Chart(timeCtx, {
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
      }
    }

    const statusRes = await fetchJSON(`${base}&action=status_summary`);
    const statusCtx = document.getElementById('statusSummaryChart')?.getContext('2d');

    if (statusCtx && statusRes.ok && statusRes.data) {
      const statuses = ['Pending', 'Confirmed', 'In Progress', 'Completed', 'Cancelled'];
      const counts = statuses.map(status => statusRes.data[status] || 0);

      const total = counts.reduce((a, b) => a + b, 0);
      if (total === 0) {
        statusCtx.canvas.parentElement.innerHTML += '<div class="text-muted text-center mt-2">No request status data.</div>';
      } else {
        window.statusChart = new Chart(statusCtx, {
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
      }
    }
   
    const topSkillsContainer = document.getElementById('topSkillsContainer');
    if (topSkillsContainer) {
      const topRes = await fetchJSON(`${base}&action=top_skills`);

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
  //            SEARCH & FILTER LOGIC
  // ======================================================
  const skillsFilterForm = document.getElementById('skillsFilterForm');
  const skillsSearchInput = document.getElementById('searchSkill');
  const filterCategorySelect = document.getElementById('filterCategory');
  const clearFiltersBtn = document.getElementById('clearFilters');

  if (filterCategorySelect && skillsSearchInput) {
    filterCategorySelect.addEventListener('change', () => {
      skillsSearchInput.value = '';
    });
  }

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (skillsFilterForm) skillsFilterForm.reset();
      if (skillsSearchInput) skillsSearchInput.value = '';
      if (filterCategorySelect) filterCategorySelect.value = '';
      window.location.href = window.location.pathname;
    });
  }

  document.querySelectorAll('.toast').forEach(toastEl => {
    new bootstrap.Toast(toastEl, { delay: 4000 }).show();
  });

  if (window.success_message) showToast(window.success_message, 'success');
  if (window.error_message) showToast(window.error_message, 'danger');

  // ============================================
  // PROFILE DROPDOWN FUNCTIONALITY
  // ============================================
  const profileTrigger = document.getElementById('profileTrigger');
  const profileDropdown = document.getElementById('profileDropdown');

  if (profileTrigger && profileDropdown) {
    profileTrigger.addEventListener('click', (e) => {
      e.stopPropagation();
      profileTrigger.classList.toggle('active');
      profileDropdown.classList.toggle('show');
    });
    
    document.addEventListener('click', (e) => {
      if (!profileTrigger.contains(e.target) && !profileDropdown.contains(e.target)) {
        profileTrigger.classList.remove('active');
        profileDropdown.classList.remove('show');
      }
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        profileTrigger.classList.remove('active');
        profileDropdown.classList.remove('show');
      }
    });
  }

  // ============================================
  // PROFILE TAB SWITCHING
  // ============================================
  document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.profile-tab-content').forEach(c => c.classList.remove('active'));
      
      this.classList.add('active');
      const tabName = this.getAttribute('data-tab');
      const target = document.getElementById(`profileTab-${tabName}`);
      if (target) target.classList.add('active');
    });
  });
  
  // ============================================
  // BASIC INFO FORM SUBMISSION
  // ============================================
  const basicForm = document.getElementById('basicInfoForm');
  if (basicForm) {
    basicForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      formData.append('action', 'update_basic_info');
      
      const dob = formData.get('dob');
      if (dob) {
        const dobDate = new Date(dob);
        const age = (new Date() - dobDate) / (365.25 * 24 * 60 * 60 * 1000);
        if (age < 13) {
          showToast('You must be at least 13 years old', 'danger');
          return;
        }
      }
      
      const btn = e.target.querySelector('.btn-primary');
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '⏳ Saving...';
      
      try {
        const res = await fetch('update_profile_provider.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.success) {
          showToast(data.message, 'success');
          
          const profileName = document.querySelector('.profile-name');
          if (profileName) {
            profileName.textContent = `Hi, ${data.data.FName}`;
          }
          
          const dropdownHeader = document.querySelector('.dropdown-user-info h4');
          if (dropdownHeader) {
            dropdownHeader.textContent = `${data.data.FName} ${data.data.LName}`;
          }
          
          const initials = (data.data.FName.charAt(0) + data.data.LName.charAt(0)).toUpperCase();
          document.querySelectorAll('.profile-avatar-initials, .dropdown-avatar-initials').forEach(el => {
            el.textContent = initials;
          });
        } else {
          showToast(data.message, 'danger');
        }
      } catch (err) {
        console.error('Error:', err);
        showToast('Error updating profile', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });
  }

  // ============================================
  // ADDRESS FORM SUBMISSION
  // ============================================
  const addressForm = document.getElementById('addressForm');
  if (addressForm) {
    addressForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      formData.append('action', 'update_address');
      
      const btn = e.target.querySelector('.btn-primary');
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '⏳ Saving...';
      
      try {
        const res = await fetch('update_profile_provider.php', { method: 'POST', body: formData });
        const data = await res.json();
        showToast(data.message, data.success ? 'success' : 'danger');
      } catch (err) {
        console.error('Error:', err);
        showToast('Error updating address', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });
  }

  // ============================================
  // PASSWORD FORM SUBMISSION
  // ============================================
  const passwordForm = document.getElementById('passwordForm');
  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      
      if (formData.get('new_password') !== formData.get('confirm_password')) {
        showToast('Passwords do not match', 'danger');
        return;
      }
      
      if (formData.get('new_password').length < 6) {
        showToast('Password must be at least 6 characters', 'danger');
        return;
      }
      
      formData.append('action', 'change_password');
      
      const btn = e.target.querySelector('.btn-primary');
      const originalText = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '⏳ Changing...';
      
      try {
        const res = await fetch('update_profile_provider.php', { method: 'POST', body: formData });
        const data = await res.json();
        
        showToast(data.message, data.success ? 'success' : 'danger');
        
        if (data.success) {
          e.target.reset();
        }
      } catch (err) {
        console.error('Error:', err);
        showToast('Error changing password', 'danger');
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
      }
    });
  }

}); // ← END OF DOMContentLoaded


// ============================================
// GLOBAL FUNCTIONS (OUTSIDE DOMContentLoaded)
// ============================================

function showToast(message, type = 'success') {
  const toastArea = document.getElementById('toast-area');
  if (!toastArea) {
    console.log('📢 Toast:', message);
    return;
  }

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

// ============================================
// MODAL FUNCTIONS - GLOBAL SCOPE
// ============================================
window.openModal = function(modalId) {
  console.log('🔓 Opening modal:', modalId);
  const modal = document.getElementById(modalId);
  
  if (!modal) {
    console.error('❌ Modal not found:', modalId);
    return;
  }
  
  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  
  requestAnimationFrame(() => {
    modal.classList.add('show');
    
    if (modalId === 'profileModal') {
      console.log('📝 Loading profile data...');
      setTimeout(() => loadProfileData(), 100);
    }
    
    if (modalId === 'photoModal') {
      console.log('📷 Initializing photo upload...');
      setTimeout(() => initPhotoPreview(), 100);
    }
  });
};

window.closeModal = function(modalId) {
  console.log('🔒 Closing modal:', modalId);
  const modal = document.getElementById(modalId);
  if (!modal) return;
  
  modal.classList.remove('show');
  setTimeout(() => {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
  }, 300);
};

// ============================================
// PROFILE DATA LOADING
// ============================================
async function loadProfileData() {
  console.log('📡 Fetching profile data from server...');
  
  try {
    const res = await fetch('update_profile_provider.php?action=get_profile', { 
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    
    const data = await res.json();
    console.log('📦 Profile data received:', data);
    
    if (data.success) {
      const user = data.data;
      const fields = {
        fname: user.FName || '',
        lname: user.LName || '',
        mname: user.MName || '',
        phone: user.Phone || '',
        dob: user.DateOfBirth || '',
        bio: user.Bio || '',
        location: user.Location || '',
        city: user.City || '',
        province: user.Province || '',
        barangay: user.Barangay || ''
      };
      
      Object.entries(fields).forEach(([id, value]) => {
        const el = document.getElementById(id);
        if (el) {
          el.value = value;
          console.log(`✅ Set ${id}:`, value);
        } else {
          console.warn(`⚠️ Field not found: ${id}`);
        }
      });
      
      console.log('✅ Profile data loaded successfully');
    } else {
      console.error('❌ Server error:', data.message);
      showToast(data.message || 'Failed to load profile', 'danger');
    }
  } catch (err) {
    console.error('❌ Network error loading profile:', err);
    showToast('Error loading profile', 'danger');
  }
}

// ============================================
// PHOTO UPLOAD FUNCTIONALITY
// ============================================
function initPhotoPreview() {
  console.log('📸 Initializing photo preview...');
  
  const preview = document.getElementById('photoPreview');
  const input = document.getElementById('photoInput');
  const uploadBtn = document.getElementById('uploadPhotoBtn');
  const removeBtn = document.getElementById('removePhotoBtn');
  
  if (!preview || !input || !uploadBtn || !removeBtn) {
    console.error('❌ Photo modal elements not found:', {
      preview: !!preview,
      input: !!input,
      uploadBtn: !!uploadBtn,
      removeBtn: !!removeBtn
    });
    return;
  }
  
  console.log('✅ Photo elements found, setting up listeners...');
  
  // Click to upload
  preview.onclick = () => {
    console.log('📁 Opening file picker...');
    input.click();
  };
  
  // Drag & drop
  preview.ondragover = (e) => {
    e.preventDefault();
    preview.style.borderColor = '#667eea';
  };
  
  preview.ondragleave = () => {
    preview.style.borderColor = '#e9ecef';
  };
  
  preview.ondrop = (e) => {
    e.preventDefault();
    preview.style.borderColor = '#e9ecef';
    if (e.dataTransfer.files[0]) {
      console.log('📥 File dropped:', e.dataTransfer.files[0].name);
      handlePhotoFile(e.dataTransfer.files[0]);
    }
  };
  
  // File input change
  input.onchange = () => {
    console.log('📁 File selected:', input.files[0]?.name);
    if (input.files[0]) {
      handlePhotoFile(input.files[0]);
    }
  };
  
  // Handle photo file
  function handlePhotoFile(file) {
    console.log('🔍 Validating file:', file.name, file.type, `${(file.size/1024/1024).toFixed(2)}MB`);
    
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!allowedTypes.includes(file.type)) {
      console.error('❌ Invalid file type:', file.type);
      showToast('Invalid file type. Use JPG, PNG, GIF, or WebP', 'danger');
      return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
      console.error('❌ File too large:', file.size);
      showToast('File too large. Maximum 5MB', 'danger');
      return;
    }
    
    console.log('✅ File valid, creating preview...');
    
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:12px;">`;
      uploadBtn.disabled = false;
      console.log('✅ Preview created, upload button enabled');
    };
    reader.readAsDataURL(file);
  }
  
  // Upload button
  uploadBtn.onclick = async () => {
    if (!input.files[0]) {
      console.warn('⚠️ No file selected');
      return;
    }
    
    console.log('📤 Uploading photo...');
    
    const formData = new FormData();
    formData.append('action', 'upload_photo');
    formData.append('photo', input.files[0]);
    
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '⏳ Uploading...';
    
    try {
      const res = await fetch('update_profile_provider.php', { 
        method: 'POST', 
        body: formData 
      });
      
      const data = await res.json();
      console.log('📦 Upload response:', data);
      
      if (data.success) {
        console.log('✅ Upload successful:', data.photo_url);
        showToast(data.message, 'success');
        closeModal('photoModal');
        setTimeout(() => location.reload(), 1000);
      } else {
        console.error('❌ Upload failed:', data.message);
        showToast(data.message, 'danger');
      }
    } catch (err) {
      console.error('❌ Network error during upload:', err);
      showToast('Error uploading photo', 'danger');
    } finally {
      uploadBtn.disabled = false;
      uploadBtn.innerHTML = 'Upload';
    }
  };
  
  // Remove button
  removeBtn.onclick = async () => {
    if (!confirm('Remove your profile photo?')) return;
    
    console.log('🗑️ Removing photo...');
    
    removeBtn.disabled = true;
    removeBtn.innerHTML = '⏳ Removing...';
    
    try {
      const res = await fetch('update_profile_provider.php?action=remove_photo', { 
        method: 'POST' 
      });
      
      const data = await res.json();
      console.log('📦 Remove response:', data);
      
      if (data.success) {
        console.log('✅ Photo removed');
        showToast(data.message, 'success');
        closeModal('photoModal');
        setTimeout(() => location.reload(), 1000);
      } else {
        console.error('❌ Remove failed:', data.message);
        showToast(data.message, 'danger');
      }
    } catch (err) {
      console.error('❌ Network error during removal:', err);
      showToast('Error removing photo', 'danger');
    } finally {
      removeBtn.disabled = false;
      removeBtn.innerHTML = 'Remove Photo';
    }
  };
  
  console.log('✅ Photo preview fully initialized');
}

// ============================================
// CLOSE MODALS ON ESC AND OUTSIDE CLICK
// ============================================
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal.show').forEach(modal => {
      closeModal(modal.id);
    });
  }
});

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal') && e.target.classList.contains('show')) {
    closeModal(e.target.id);
  }
});

console.log('✅ Provider.js fully loaded and ready');