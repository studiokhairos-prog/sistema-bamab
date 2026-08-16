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

// Aviso persistente de novas matrículas no painel administrativo.
(()=>{
  const app=document.getElementById('enrollmentNotificationApp');
  if(!app)return;
  const endpoint=app.dataset.endpoint||'';
  const csrf=app.dataset.csrf||'';
  const summary=app.querySelector('[data-notification-summary]');
  const list=app.querySelector('[data-notification-list]');
  const readButton=app.querySelector('[data-notification-read]');
  const closeButton=app.querySelector('[data-notification-close]');
  let visibleIds=[];
  let visibleKey='';
  let loading=false;

  const dateLabel=value=>{
    const date=new Date(String(value||'').replace(' ','T'));
    if(Number.isNaN(date.getTime()))return '';
    return new Intl.DateTimeFormat('pt-BR',{dateStyle:'short',timeStyle:'short'}).format(date);
  };

  const markRead=async ids=>{
    if(!ids.length)return true;
    const body=new FormData();
    body.append('csrf',csrf);
    body.append('action','mark_read');
    ids.forEach(id=>body.append('ids[]',String(id)));
    try{
      const response=await fetch(endpoint,{method:'POST',body,credentials:'same-origin',cache:'no-store'});
      return response.ok;
    }catch(error){
      return false;
    }
  };

  const hide=()=>{
    app.classList.remove('is-visible');
    app.hidden=true;
    visibleIds=[];
    visibleKey='';
  };

  const acknowledge=async()=>{
    const ids=[...visibleIds];
    readButton.disabled=true;
    closeButton.disabled=true;
    const ok=await markRead(ids);
    readButton.disabled=false;
    closeButton.disabled=false;
    if(ok)hide();
    else if(summary)summary.textContent='Não foi possível marcar o aviso como visto. Tente novamente.';
  };

  const render=(items,total)=>{
    const key=items.map(item=>item.id).join(',');
    if(!items.length){hide();return;}
    if(!list||!summary||key===visibleKey)return;
    visibleKey=key;
    visibleIds=items.map(item=>Number(item.id)).filter(Number.isInteger);
    summary.textContent=total===1
      ?'Uma nova matrícula foi recebida e aprovada automaticamente.'
      :`${total} novas matrículas foram recebidas e aprovadas automaticamente.`;
    list.replaceChildren();

    items.forEach(item=>{
      const article=document.createElement('article');
      const info=document.createElement('div');
      const name=document.createElement('strong');
      const details=document.createElement('span');
      const time=document.createElement('small');
      const link=document.createElement('a');
      name.textContent=item.student_name||'Aluno(a)';
      details.textContent=[item.registration_number,item.instrument].filter(Boolean).join(' · ');
      time.textContent=dateLabel(item.created_at);
      link.href=item.url;
      link.textContent='ABRIR E EDITAR';
      link.addEventListener('click',async event=>{
        event.preventDefault();
        link.classList.add('is-loading');
        await markRead([Number(item.id)]);
        window.location.href=item.url;
      });
      info.append(name,details,time);
      article.append(info,link);
      list.append(article);
    });

    readButton.textContent=items.length===1?'MARCAR COMO VISTA':'MARCAR TODAS COMO VISTAS';
    app.hidden=false;
    requestAnimationFrame(()=>app.classList.add('is-visible'));
  };

  const poll=async()=>{
    if(loading||document.hidden)return;
    loading=true;
    try{
      const response=await fetch(endpoint,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      if(response.status===401){hide();return;}
      if(!response.ok)return;
      const data=await response.json();
      if(data&&data.ok)render(Array.isArray(data.notifications)?data.notifications:[],Number(data.unread_count)||0);
    }catch(error){
      // Mantém o painel funcional mesmo durante uma falha temporária de rede.
    }finally{
      loading=false;
    }
  };

  readButton?.addEventListener('click',acknowledge);
  closeButton?.addEventListener('click',acknowledge);
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!app.hidden)acknowledge();});
  document.addEventListener('visibilitychange',()=>{if(!document.hidden)poll();});
  poll();
  window.setInterval(poll,30000);
})();
