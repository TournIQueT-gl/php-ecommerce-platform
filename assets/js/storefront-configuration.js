(()=>{'use strict';
const form=document.querySelector('[data-sc-form]');if(!form)return;
const items=form.querySelector('[data-sc-items]');const preview=form.querySelector('[data-sc-preview]');const nav=form.querySelector('[data-sc-preview-nav]');
const depts=()=>Array.from(form.querySelectorAll('[data-sc-department]:checked')).map(e=>e.value);
let seq=0;
function renumber(){items.querySelectorAll('[data-sc-row]').forEach((row,i)=>{
 for(const el of row.querySelectorAll('[name^="link_"]')){el.name=el.name.replace(/\[\d+\]/,`[${i}]`);}
 const type=row.querySelector('[data-sc-type]');const code=row.querySelector('[data-sc-code]');
 code.disabled=type.value!=='department';
 });}
function paint(){renumber();const codes=depts();const style=form.querySelector('[data-sc-style]')?.value||'auto';
 const mode=preview.dataset.mode||'desktop';const visible=[];
 const hasAudienceDept=[...form.querySelectorAll('[name="audience_departments[]"]:checked')].some(x=>codes.includes(x.value));
 if(hasAudienceDept)form.querySelectorAll('[data-sc-audience]:checked').forEach(x=>visible.push(x.value.charAt(0).toUpperCase()+x.value.slice(1)+' ⌄'));
 items.querySelectorAll('[data-sc-row]').forEach(row=>{
 const flag=row.querySelector(mode==='desktop'?'[data-sc-desktop]':'[data-sc-mobile]');if(!flag?.checked)return;
 const type=row.querySelector('[data-sc-type]').value;
 if(['men','women','unisex'].includes(type)&&hasAudienceDept&&[...form.querySelectorAll('[data-sc-audience]:checked')].some(x=>x.value===type))return;
 const code=row.querySelector('[data-sc-code]').value;
 if(type==='department'&&!codes.includes(code))return;
 let label=row.querySelector('[data-sc-label]').value.trim()||type;
 if(type==='shop'){label+=codes.length>0?` · ${codes.length} departments`: ' · All products';}
 if(type==='department'){const d=Array.from(form.querySelectorAll('[data-sc-department]')).find(x=>x.value===code);label+=d?' · '+d.closest('label').querySelector('strong').textContent:'';}
 visible.push(label);
 });
 nav.replaceChildren(...visible.map(label=>{const s=document.createElement('span');s.textContent=label;return s;}));
 preview.classList.toggle('sc-preview-mega',style==='mega');
}
items.addEventListener('click',e=>{const row=e.target.closest('[data-sc-row]');if(!row)return;
 if(e.target.closest('[data-sc-remove]')){row.remove();paint();}
 if(e.target.closest('[data-sc-up]')){const previous=row.previousElementSibling;if(previous)items.insertBefore(row,previous);paint();}
 if(e.target.closest('[data-sc-down]')){const next=row.nextElementSibling;if(next)items.insertBefore(next,row);paint();}
});
form.querySelector('[data-sc-add]')?.addEventListener('click',()=>{
 if(items.querySelectorAll('[data-sc-row]').length>=12)return;
 const row=items.querySelector('[data-sc-row]')?.cloneNode(true);if(!row)return;
 row.querySelector('[data-sc-label]').value='';row.querySelector('[data-sc-type]').value='catalog';row.querySelector('[data-sc-code]').value='';
 row.querySelector('[data-sc-desktop]').checked=true;row.querySelector('[data-sc-mobile]').checked=true;
 items.append(row);paint();row.querySelector('[data-sc-label]').focus();
});
form.addEventListener('input',paint);form.addEventListener('change',paint);
form.querySelectorAll('[data-sc-mode]').forEach(btn=>btn.addEventListener('click',()=>{preview.dataset.mode=btn.dataset.scMode;form.querySelectorAll('[data-sc-mode]').forEach(b=>b.classList.toggle('is-active',b===btn));paint();}));
form.addEventListener('submit',e=>{renumber();const rows=items.querySelectorAll('[data-sc-row]');if(!rows.length){e.preventDefault();alert('Add at least one navigation link.');return;}
 for(const row of rows){if(row.querySelector('[data-sc-type]').value==='department'&&!depts().includes(row.querySelector('[data-sc-code]').value)){e.preventDefault();alert('Choose a selected department for the department link, or remove the link.');return;}}
});
paint();
})();
