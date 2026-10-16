(()=>{'use strict';
 const filter=document.querySelector('[data-builder-template-filter]');
 const gallery=document.querySelector('[data-builder-template-gallery]');
 if(filter&&gallery){
  const cards=[...gallery.querySelectorAll('[data-builder-template-label]')];
  const count=document.querySelector('[data-builder-template-count]');
  const update=()=>{const q=filter.value.trim().toLocaleLowerCase();let visible=0;
   cards.forEach(card=>{const match=(card.dataset.builderTemplateLabel||'').toLocaleLowerCase().includes(q);card.hidden=!match;if(match)visible++;});
   if(count)count.textContent=`${visible} of ${cards.length} layouts`;};
  filter.addEventListener('input',update);update();
 }
 const listFilter=document.querySelector('[data-uc-visible-filter]');
 const list=document.querySelector('[data-uc-visible-table]');
 if(listFilter&&list){
  const rows=[...list.querySelectorAll('tbody>tr')];const count=document.querySelector('[data-uc-visible-count]');
  const update=()=>{const q=listFilter.value.trim().toLocaleLowerCase();let shown=0;
   rows.forEach(row=>{const match=row.textContent.toLocaleLowerCase().includes(q);row.hidden=!match;if(!match){const select=row.querySelector('[data-uc-bulk-item]');if(select)select.checked=false;}if(match)shown++;});
   list.dispatchEvent(new Event('uc:visible-rows-updated'));
   if(count){const ar=document.documentElement.lang?.toLowerCase().startsWith('ar');count.textContent=ar?`${shown} من ${rows.length} منتج في هذه الصفحة`:`${shown} of ${rows.length} products on this page`;}};
  listFilter.addEventListener('input',update);update();
 }
 const bulk=document.querySelector('[data-uc-bulk-products]');
 if(bulk){
  const all=bulk.querySelector('[data-uc-bulk-all]');
  const choices=[...bulk.querySelectorAll('[data-uc-bulk-item]')];
  const action=bulk.querySelector('[data-uc-bulk-action]');
  const submit=bulk.querySelector('[data-uc-bulk-submit]');
  const message=bulk.querySelector('[data-uc-bulk-count]');
  const language=document.documentElement.lang?.toLowerCase().startsWith('ar')?'ar':'en';
  const visible=()=>choices.filter(input=>!input.closest('tr')?.hidden);
  const sync=()=>{
    const selected=choices.filter(input=>input.checked).length;
    const shown=visible();const shownSelected=shown.filter(input=>input.checked).length;
    if(all){all.checked=shown.length>0&&shownSelected===shown.length;all.indeterminate=shownSelected>0&&shownSelected<shown.length;all.disabled=shown.length===0;}
    if(submit)submit.disabled=selected===0||bulk.dataset.submitting==='1';
    if(message)message.textContent=language==='ar'?`${selected} منتج محدد`:`${selected} selected`;
  };
  all?.addEventListener('change',()=>{visible().forEach(input=>{input.checked=all.checked;});sync();});
  choices.forEach(input=>input.addEventListener('change',sync));
  bulk.querySelector('[data-uc-visible-table]')?.addEventListener('uc:visible-rows-updated',sync);
  bulk.addEventListener('submit',event=>{
    const count=choices.filter(input=>input.checked).length;
    if(count===0||bulk.dataset.submitting==='1'){event.preventDefault();return;}
    const archive=action?.value==='archive';
    const ask=language==='ar'?(archive?`أرشفة ${count} منتج؟ لن يتم حذف الطلبات أو المخزون.`:`استعادة ${count} منتج كمسودات؟`):(archive?`Archive ${count} product(s)? Orders and stock will not be deleted.`:`Restore ${count} product(s) as drafts?`);
    if(!window.confirm(ask)){event.preventDefault();return;}
    bulk.dataset.submitting='1';sync();
  });
  sync();
 }
})();

/* RC14: section search is purely visual; it never mutates saved homepage order. */
(()=>{'use strict';
 const tools=document.querySelector('[data-builder-section-tools]');
 const list=document.querySelector('[data-homepage-section-list]');
 if(!tools||!list)return;
 const query=tools.querySelector('[data-builder-section-search]');
 const status=tools.querySelector('[data-builder-section-status]');
 const count=tools.querySelector('[data-builder-section-visible-count]');
 const cards=[...list.querySelectorAll('[data-homepage-section-card]')];
 const apply=()=>{
   const needle=(query?.value||'').trim().toLocaleLowerCase();
   const selected=status?.value||'all';let shown=0;
   cards.forEach(card=>{
     const matchText=!needle||(card.dataset.searchLabel||card.textContent||'').toLocaleLowerCase().includes(needle);
     const matchStatus=selected==='all'||card.dataset.status===selected;
     const display=matchText&&matchStatus;
     card.hidden=!display;
     if(display)shown++;
   });
   const ar=document.documentElement.lang?.toLowerCase().startsWith('ar');
   if(count)count.textContent=ar?`${shown} من ${cards.length} قسم`:`${shown} of ${cards.length} sections`;
 };
 query?.addEventListener('input',apply);status?.addEventListener('change',apply);apply();
})();
