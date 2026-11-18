'use strict';
const menu=document.querySelector('[data-menu]');
menu?.addEventListener('click',()=>{const open=document.body.classList.toggle('menu-open');menu.setAttribute('aria-expanded',String(open));});
document.querySelectorAll('[data-print]').forEach(b=>b.addEventListener('click',()=>window.print()));
document.querySelectorAll('[data-confirm]').forEach(b=>b.addEventListener('click',e=>{if(!window.confirm(b.dataset.confirm))e.preventDefault();}));
document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-filter]').forEach(x=>x.classList.toggle('active',x===b));let visible=0;document.querySelectorAll('[data-subject-state]').forEach(card=>{card.hidden=b.dataset.filter!=='all'&&card.dataset.subjectState!==b.dataset.filter;if(!card.hidden)visible++;});const empty=document.querySelector('[data-filter-empty]');if(empty)empty.hidden=visible>0;}));
const exam=document.getElementById('exam-form');
if(exam){
  let timer,dirty=false,submitting=false,saving=false;
  const status=document.getElementById('draft-status');
  const answers=()=>{const data={};new FormData(exam).forEach((value,key)=>{const match=key.match(/^answers\[(.+)\]$/);if(match&&String(value).trim())data[match[1]]=String(value);});return data;};
  const save=async()=>{if(saving||submitting)return;saving=true;const snapshot=JSON.stringify(answers());status.textContent='Salvando rascunho…';try{const response=await fetch(exam.dataset.draftUrl,{method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({answers:JSON.parse(snapshot)})});if(!response.ok)throw new Error();const data=await response.json();dirty=snapshot!==JSON.stringify(answers());status.textContent='Rascunho salvo às '+data.saved_at;if(dirty)timer=setTimeout(save,800);}catch{status.textContent='Não foi possível salvar. Confira sua conexão.';}finally{saving=false;}};
  exam.addEventListener('input',()=>{dirty=true;clearTimeout(timer);timer=setTimeout(save,900);});
  exam.addEventListener('submit',e=>{if(!window.confirm('Enviar a prova? As respostas desta tentativa não poderão ser alteradas.')){e.preventDefault();return;}submitting=true;clearTimeout(timer);const button=document.getElementById('submit-exam');button.disabled=true;button.textContent='Enviando prova…';});
  window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue='';}});
}
