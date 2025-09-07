document.addEventListener('DOMContentLoaded', () => {
  // === NAVIGATION LINKS ===
  const postServiceLink = document.getElementById('postServiceLink');
  const skillsLink = document.getElementById('skillsLink');
  const jobsLink = document.getElementById('jobsLink');

  // === SECTIONS (from PHP) ===
  const postServiceSection = document.getElementById('add-skill');
  const skillsSection = document.getElementById('skills-section');
  const jobsSection = document.getElementById('jobs-section');

  // === FORM ELEMENTS ===
  const categorySelect = document.getElementById('category');
  const otherCategoryGroup = document.getElementById('otherCategoryGroup');
  const otherCategoryInput = document.getElementById('otherCategory');

  // === CURRENCY FORMATTER for ALL "rate" inputs ===
  document.querySelectorAll("input[name='rate']").forEach((rateInput) => {
    // On focus: show raw number
    rateInput.addEventListener("focus", (e) => {
      let raw = e.target.value.replace(/[₱,]/g, "").replace(/,/g, "");
      e.target.value = raw || "";
    });

    // On blur: format with peso currency
    rateInput.addEventListener("blur", (e) => {
      let value = e.target.value.replace(/[^\d.]/g, "");
      if (value) {
        let num = parseFloat(value);
        if (!isNaN(num)) {
          e.target.value = num.toLocaleString("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 2,
          });
        }
      }
    });

    // On submit: convert back to plain number
    if (rateInput.form) {
      rateInput.form.addEventListener("submit", () => {
        const raw = rateInput.value.replace(/[₱,]/g, "").replace(/,/g, "");
        rateInput.value = raw || "";
      });
    }
  });

  // === HELPER: SHOW ACTIVE SECTION ===
  function showSection(section) {
    [postServiceSection, skillsSection, jobsSection].forEach(sec => {
      sec.classList.remove('active');
    });
    section.classList.add('active');

    // Update nav highlight
    document.querySelectorAll('.nav-links a').forEach(link => {
      link.classList.remove('active');
    });
    if (section === postServiceSection) postServiceLink.classList.add('active');
    if (section === skillsSection) skillsLink.classList.add('active');
    if (section === jobsSection) jobsLink.classList.add('active');

    // Smooth scroll to top
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // === NAVIGATION EVENTS ===
  postServiceLink.addEventListener('click', e => {
    e.preventDefault();
    showSection(postServiceSection);
    categorySelect.focus();
  });

  skillsLink.addEventListener('click', e => {
    e.preventDefault();
    showSection(skillsSection);
  });

  jobsLink.addEventListener('click', e => {
    e.preventDefault();
    showSection(jobsSection);
  });

  // === Show/hide "Other" category input ===
  categorySelect.addEventListener('change', () => {
    if (categorySelect.value === 'others') {
      otherCategoryGroup.style.display = 'block';
      otherCategoryInput.required = true;
    } else {
      otherCategoryGroup.style.display = 'none';
      otherCategoryInput.required = false;
    }
  });

  // === AUTO-HIDE success/error messages ===
  const messages = document.querySelectorAll('.success-message, .error-message');
  messages.forEach(msg => {
    setTimeout(() => {
      msg.classList.add('fade-out');
      // remove from DOM after fade
      setTimeout(() => msg.remove(), 600);
    }, 3000);
  });

  // === DEFAULT SECTION ON LOAD ===
  showSection(skillsSection);

  //add skill first redirect
  
});

document.addEventListener('DOMContentLoaded', function() {
    function showSection(sectionId) {
        document.querySelectorAll('.dashboard-container > section').forEach(sec => sec.style.display = 'none');
        document.getElementById(sectionId).style.display = '';
    }

    document.getElementById('dashboard').addEventListener('click', function(e) {
        e.preventDefault();
        showSection('add-skill');
    });
    document.getElementById('postServiceLink').addEventListener('click', function(e) {
        e.preventDefault();
        showSection('add-skill');
    });
    document.getElementById('skillsLink').addEventListener('click', function(e) {
        e.preventDefault();
        showSection('skills-section');
    });
    document.getElementById('jobsLink').addEventListener('click', function(e) {
        e.preventDefault();
        showSection('jobs-section');
    });

    // Show dashboard by default
    showSection('add-skill');
});
