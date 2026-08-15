document.querySelectorAll('textarea').forEach(t=>{
  const resize=()=>{if(t.scrollHeight<500){t.style.height='auto';t.style.height=Math.min(500,t.scrollHeight)+'px';}};
  t.addEventListener('input',resize);
});

(()=>{
  const button=document.querySelector('.admin-menu-btn');
  const nav=document.getElementById('adminNav');
  if(!button||!nav)return;
  const close=()=>{nav.classList.remove('open');button.setAttribute('aria-expanded','false');};
  button.addEventListener('click',()=>{
    const open=nav.classList.toggle('open');
    button.setAttribute('aria-expanded',open?'true':'false');
  });
  nav.addEventListener('click',e=>{if(e.target.closest('a')&&window.matchMedia('(max-width: 1050px)').matches)close();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
  window.addEventListener('resize',()=>{if(window.innerWidth>1050)close();});
})();

// Feedback visual de envio sem remover o valor do botão acionado.
document.querySelectorAll('form').forEach(form=>{
  form.addEventListener('submit',()=>form.classList.add('is-submitting'));
});
