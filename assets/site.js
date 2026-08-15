(()=>{
  const button=document.querySelector('.menu-btn');
  const nav=document.querySelector('.main-nav');
  if(button&&nav){
    const close=()=>{nav.classList.remove('open');button.setAttribute('aria-expanded','false');button.setAttribute('aria-label','Abrir menu');};
    button.addEventListener('click',()=>{
      const open=nav.classList.toggle('open');
      button.setAttribute('aria-expanded',open?'true':'false');
      button.setAttribute('aria-label',open?'Fechar menu':'Abrir menu');
    });
    nav.addEventListener('click',e=>{if(e.target.closest('a')&&window.matchMedia('(max-width: 900px)').matches)close();});
    document.addEventListener('keydown',e=>{if(e.key==='Escape')close();});
    window.addEventListener('resize',()=>{if(window.innerWidth>900)close();});
  }

  const login=document.querySelector('.site-login-menu');
  document.addEventListener('click',e=>{if(login?.open&&!login.contains(e.target))login.removeAttribute('open');});
})();

// Rotação independente de patrocinadores e apoios institucionais.
(()=>{
  const widgets=[...document.querySelectorAll('[data-rotating-logos]')];
  if(!widgets.length) return;
  const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  widgets.forEach(widget=>{
    const slides=[...widget.querySelectorAll('.sponsor-slide')];
    const dots=[...widget.querySelectorAll('[data-sponsor-go]')];
    const progress=widget.querySelector('.sponsor-progress i');
    const current=widget.querySelector('[data-sponsor-current]');
    if(!slides.length) return;
    let index=0,timer=null,cycleToken=0;
    const durationFor=i=>Math.max(3000,Math.min(60000,Number(slides[i]?.dataset.duration||6000)));
    function stop(){if(timer){clearTimeout(timer);timer=null;}cycleToken++;}
    function animateProgress(ms,token){
      if(!progress) return;
      progress.style.transition='none';progress.style.width=reduced?'100%':'0%';
      if(reduced)return;
      requestAnimationFrame(()=>requestAnimationFrame(()=>{
        if(token!==cycleToken) return;
        progress.style.transition=`width ${ms}ms linear`;progress.style.width='100%';
      }));
    }
    function show(next,auto=true){
      stop();index=(next+slides.length)%slides.length;
      slides.forEach((slide,i)=>{const active=i===index;slide.classList.toggle('is-active',active);slide.setAttribute('aria-hidden',active?'false':'true');});
      dots.forEach((dot,i)=>{dot.classList.toggle('is-active',i===index);dot.setAttribute('aria-current',i===index?'true':'false');});
      if(current) current.textContent=String(index+1);
      const token=cycleToken,ms=durationFor(index);animateProgress(ms,token);
      if(auto&&!reduced&&slides.length>1) timer=setTimeout(()=>show(index+1,true),ms);
      else if(slides.length===1&&progress){progress.style.transition='none';progress.style.width='100%';}
    }
    dots.forEach((dot,i)=>dot.addEventListener('click',()=>show(i,true)));
    widget.addEventListener('mouseenter',stop);
    widget.addEventListener('mouseleave',()=>{if(!reduced)show(index,true);});
    widget.addEventListener('focusin',stop);
    widget.addEventListener('focusout',()=>{if(!reduced)show(index,true);});
    document.addEventListener('visibilitychange',()=>{if(document.hidden)stop();else show(index,true);});
    show(0,true);
  });
})();
