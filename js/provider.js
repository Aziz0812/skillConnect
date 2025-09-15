document.addEventListener('DOMContentLoaded', () => {
  // === NAVIGATION LINKS ===
  const postServiceLink = document.getElementById('postServiceLink');
  const skillsLink = document.getElementById('skillsLink');
  const jobsLink = document.getElementById('jobsLink');

  // === SECTIONS ===
  const postServiceSection = document.getElementById('add-skill');
  const skillsSection = document.getElementById('skills-section');
  const jobsSection = document.getElementById('jobs-section');

  const allSections = [postServiceSection, skillsSection, jobsSection];

  // === FORM ELEMENTS ===
  const categorySelect = document.getElementById('category');
  const otherCategoryGroup = document.getElementById('otherCategoryGroup');
  const otherCategoryInput = document.getElementById('otherCategory');

  // === CURRENCY FORMATTER ... (your existing code for rate inputs) ===
  // [keep your existing code here, unchanged]

  // === Show/hide "Other" category input ===
  categorySelect?.addEventListener('change', () => {
    if (categorySelect.value === 'others') {
      otherCategoryGroup.style.display = 'block';
      otherCategoryInput.required = true;
    } else {
      otherCategoryGroup.style.display = 'none';
      otherCategoryInput.required = false;
    }
  });

  // === Auto-hide messages ===
  document.querySelectorAll('.success-message, .error-message').forEach(msg => {
    setTimeout(() => {
      msg.classList.add('fade-out');
      setTimeout(() => msg.remove(), 600);
    }, 3000);
  });

  // === Helper: show active section ===
  function showSection(section) {
    allSections.forEach(sec => {
      sec.classList.remove('active');
      sec.style.display = 'none';  // ensure hidden
    });
    section.style.display = 'block';  // make visible
    section.classList.add('active');

    document.querySelectorAll('.nav-links a').forEach(link => link.classList.remove('active'));
    if (section === postServiceSection) postServiceLink.classList.add('active');
    if (section === skillsSection) skillsLink.classList.add('active');
    if (section === jobsSection) jobsLink.classList.add('active');

    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  // === Navigation Events ===
  postServiceLink?.addEventListener('click', e => {
    e.preventDefault();
    showSection(postServiceSection);
    localStorage.setItem('activeSection', 'add-skill'); // Save active section
    categorySelect?.focus();
  });
  skillsLink?.addEventListener('click', e => {
    e.preventDefault();
    showSection(skillsSection);
    localStorage.setItem('activeSection', 'skills-section'); // Save active section
  });
  jobsLink?.addEventListener('click', e => {
    e.preventDefault();
    showSection(jobsSection);
    localStorage.setItem('activeSection', 'jobs-section'); // Save active section
  });

  // === Default or fallback on Load ===
  // Check if any section has .active class already (maybe via server-rendered active class)
  let initiallyActive = allSections.find(sec => sec.classList.contains('active'));

  // Check localStorage first for last active section on reload
  const savedSectionId = localStorage.getItem('activeSection');
  const savedSection = savedSectionId ? document.getElementById(savedSectionId) : null;

  if (savedSection) {
    showSection(savedSection);
  } else if (initiallyActive) {
    showSection(initiallyActive);
  } else {
    // if no section is active, show a default (skills)
    showSection(skillsSection);
  }
});
