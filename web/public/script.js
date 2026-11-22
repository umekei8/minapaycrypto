const menu=document.querySelector('.menu');const nav=document.querySelector('.nav');
function closeMenu(){menu.setAttribute('aria-expanded','false');nav.classList.remove('is-open')}
menu.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));nav.classList.toggle('is-open',open)});
nav.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
window.addEventListener('resize',()=>{if(window.innerWidth>900)closeMenu()});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeMenu()});
