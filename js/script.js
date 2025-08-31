const logo = document.querySelector('.logo');
let selectedRole = null;

// Function to handle role selection
function selectRole(role) {
  selectedRole = role;

  // Remove highlight from all role cards
  document.querySelectorAll('.role-card').forEach(card => {
    card.classList.remove('active');
  });

  // Highlight the selected role card
  document.getElementById(role).classList.add('active');

  // Save role in localStorage (optional, in case PHP or other JS needs it later)
  localStorage.setItem('selectedRole', role);

  // Show the Continue button
  document.getElementById('continue-btn').style.display = 'inline-block';
}

// Handle Continue button click
document.getElementById('continue-btn').addEventListener('click', () => {
  if (selectedRole) {
    // Redirect to PHP registration page with role parameter
    window.location.href = `register.php?role=${encodeURIComponent(selectedRole)}`;
  } else {
    alert('Please select a role first.');
  }
});

// Scroll to top when logo is clicked
logo.addEventListener('click', () => {
  window.scrollTo({ top: 0, behavior: 'smooth' });
});
