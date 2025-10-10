document.addEventListener('DOMContentLoaded', () => {
    console.log('provider.js loaded'); // Debug to confirm script runs

    // NAVIGATION LINKS
    const dashboardLink = document.getElementById('dashboard');
    const postServiceLink = document.getElementById('postServiceLink');
    const skillsLink = document.getElementById('skillsLink');
    const jobsLink = document.getElementById('jobsLink');
    const addSkillFirst = document.getElementById('addSkillFirst');

    // SECTIONS
    const dashboardSection = document.getElementById('dashboard-section');
    const postServiceSection = document.getElementById('add-skill');
    const skillsSection = document.getElementById('skills-section');
    const jobsSection = document.getElementById('jobs-section');

    const allSections = [dashboardSection, postServiceSection, skillsSection, jobsSection];

    // FORM ELEMENTS
    const categorySelect = document.getElementById('category');
    const otherCategoryGroup = document.getElementById('otherCategoryGroup');
    const otherCategoryInput = document.getElementById('otherCategory');
    const rateInput = document.getElementById('rate');
    const ratePreview = document.getElementById('ratePreview');

    // Show/hide "Other" category input
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            console.log('Category changed:', categorySelect.value); // Debug
            if (categorySelect.value === 'others') {
                otherCategoryGroup.style.display = 'block';
                otherCategoryInput.required = true;
                otherCategoryInput.focus();
            } else {
                otherCategoryGroup.style.display = 'none';
                otherCategoryInput.required = false;
                otherCategoryInput.value = '';
            }
        });
    }

    // Rate preview with pesos sign
    if (rateInput && ratePreview) {
        rateInput.addEventListener('input', () => {
            console.log('Rate input:', rateInput.value); // Debug
            const rate = parseFloat(rateInput.value);
            if (isNaN(rate) || rate <= 0) {
                ratePreview.textContent = 'Please enter a valid positive rate.';
                ratePreview.style.color = 'red';
            } else {
                ratePreview.textContent = `₱${rate.toFixed(2)}/hour`;
                ratePreview.style.color = 'green';
            }
        });
        // Initial display
        ratePreview.textContent = 'Enter rate above';
    }

    // Auto-hide messages
    document.querySelectorAll('.success-message, .error-message').forEach(msg => {
        setTimeout(() => {
            msg.classList.add('fade-out');
            setTimeout(() => msg.remove(), 600);
        }, 3000);
    });

    // Helper: show active section
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
        }

        document.querySelectorAll('.nav-links a').forEach(link => link.classList.remove('active'));
        if (section === dashboardSection) dashboardLink?.classList.add('active');
        if (section === postServiceSection) postServiceLink?.classList.add('active');
        if (section === skillsSection) skillsLink?.classList.add('active');
        if (section === jobsSection) jobsLink?.classList.add('active');

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Navigation Events
    if (dashboardLink) {
        dashboardLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection(dashboardSection);
            location.hash = '#dashboard-section';
        });
    }
    if (postServiceLink) {
        postServiceLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection(postServiceSection);
            location.hash = '#add-skill';
        });
    }
    if (skillsLink) {
        skillsLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection(skillsSection);
            location.hash = '#skills-section';
        });
    }
    if (jobsLink) {
        jobsLink.addEventListener('click', (e) => {
            e.preventDefault();
            showSection(jobsSection);
            location.hash = '#jobs-section';
        });
    }
    if (addSkillFirst) {
        addSkillFirst.addEventListener('click', (e) => {
            e.preventDefault();
            showSection(postServiceSection);
            location.hash = '#add-skill';
        });
    }

    // Default or fallback on Load
    let initiallyActive = allSections.find(sec => sec && sec.classList.contains('active'));
    const savedSectionId = localStorage.getItem('activeSection');
    const savedSection = savedSectionId ? document.getElementById(savedSectionId) : null;

    if (savedSection) {
        showSection(savedSection);
    } else if (initiallyActive) {
        showSection(initiallyActive);
    } else {
        showSection(dashboardSection); // Default to dashboard
    }

    // Ensure hash-based navigation works
    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.substring(1);
        const targetSection = document.getElementById(hash);
        if (targetSection) showSection(targetSection);
    });

    // Initial hash check
    if (window.location.hash) {
        const initialSection = document.getElementById(window.location.hash.substring(1));
        if (initialSection) showSection(initialSection);
    }
});