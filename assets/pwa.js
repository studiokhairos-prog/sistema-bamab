(()=>{
  'use strict';

  const isStandalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  if('serviceWorker' in navigator){
    window.addEventListener('load',()=>{
      navigator.serviceWorker.register('service-worker.js').catch(()=>{});
    });
  }
  if(isStandalone) return;

  let installPrompt=null;
  let installButton=null;
  const isiOS=/iphone|ipad|ipod/i.test(navigator.userAgent)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);

  function button(){
    if(installButton) return installButton;
    installButton=document.createElement('button');
    installButton.type='button';
    installButton.className='pwa-install-button';
    installButton.hidden=true;
    installButton.innerHTML='<img src="assets/icons/icon-192.png" alt=""><span>INSTALAR BAMAB</span>';
    installButton.setAttribute('aria-label','Instalar o aplicativo BAMAB');
    document.body.appendChild(installButton);
    return installButton;
  }

  function closeGuide(){
    const guide=document.querySelector('.pwa-install-backdrop');
    if(guide){guide.hidden=true;document.body.style.overflow='';button().focus();}
  }

  function showIOSGuide(){
    let guide=document.querySelector('.pwa-install-backdrop');
    if(!guide){
      guide=document.createElement('div');
      guide.className='pwa-install-backdrop';
      guide.hidden=true;
      guide.innerHTML='<section class="pwa-install-dialog" role="dialog" aria-modal="true" aria-labelledby="pwaInstallTitle"><img src="assets/icons/icon-192.png" alt="Brasão BAMAB"><h2 id="pwaInstallTitle">Instalar BAMAB</h2><p>No iPhone ou iPad, faça a instalação pelo menu do navegador:</p><div class="pwa-install-steps"><strong>1. Toque em Compartilhar ⎋</strong><strong>2. Escolha “Adicionar à Tela de Início”</strong><strong>3. Confirme em “Adicionar”</strong></div><button class="pwa-install-close" type="button">ENTENDI</button></section>';
      document.body.appendChild(guide);
      guide.querySelector('.pwa-install-close').addEventListener('click',closeGuide);
      guide.addEventListener('click',event=>{if(event.target===guide)closeGuide();});
      document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!guide.hidden)closeGuide();});
    }
    guide.hidden=false;
    document.body.style.overflow='hidden';
    guide.querySelector('.pwa-install-close').focus();
  }

  if(isiOS){
    const iosButton=button();
    iosButton.hidden=false;
    iosButton.addEventListener('click',showIOSGuide);
  }

  window.addEventListener('beforeinstallprompt',event=>{
    event.preventDefault();
    installPrompt=event;
    const promptButton=button();
    promptButton.hidden=false;
    promptButton.onclick=async()=>{
      if(!installPrompt) return;
      promptButton.disabled=true;
      installPrompt.prompt();
      await installPrompt.userChoice;
      installPrompt=null;
      promptButton.hidden=true;
      promptButton.disabled=false;
    };
  });

  window.addEventListener('appinstalled',()=>{
    installPrompt=null;
    if(installButton) installButton.hidden=true;
  });
})();
