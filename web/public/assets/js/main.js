// Header scroll effect
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
  if (window.scrollY > 60) {
    header.classList.add('scrolled');
  } else {
    header.classList.remove('scrolled');
  }
}, { passive: true });

// Hamburger menu
const hamburger = document.getElementById('hamburger');
const nav = document.getElementById('nav');
hamburger.addEventListener('click', () => {
  nav.classList.toggle('open');
});
nav.querySelectorAll('a').forEach(link => {
  link.addEventListener('click', () => nav.classList.remove('open'));
});

// Contact form (demo — shows thank you message)
const form = document.getElementById('contactForm');
const notice = document.getElementById('formNotice');
const submitBtn = document.getElementById('submitBtn');

if (form) {
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    submitBtn.disabled = true;
    submitBtn.textContent = '送信中...';
    // Simulate send
    setTimeout(() => {
      notice.hidden = false;
      notice.className = 'form-notice success';
      notice.textContent = 'お問い合わせを受け付けました。担当者よりご連絡いたします。';
      form.reset();
      submitBtn.disabled = false;
      submitBtn.textContent = '送信する';
    }, 1000);
  });
}

// Scroll reveal (simple fade-in)
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.style.opacity = '1';
      entry.target.style.transform = 'translateY(0)';
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.12 });

document.querySelectorAll('.feature-card, .currency-card, .step, .pricing-card, .pricing-note').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(24px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
  observer.observe(el);
});
