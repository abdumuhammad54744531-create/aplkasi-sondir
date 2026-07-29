document.getElementById('sidebarToggle')?.addEventListener('click',()=>document.getElementById('sidebar')?.classList.toggle('show'));
document.querySelectorAll('[data-flash]').forEach(el=>Swal.fire({icon:el.dataset.flash==='danger'?'error':el.dataset.flash,title:el.dataset.message,timer:2600,showConfirmButton:false}));
document.querySelectorAll('form[data-confirm]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();Swal.fire({title:form.dataset.confirm,text:'Tindakan ini perlu dikonfirmasi.',icon:'warning',showCancelButton:true,confirmButtonText:'Ya, lanjutkan',cancelButtonText:'Batal'}).then(r=>{if(r.isConfirmed)form.submit()})}));
document.querySelectorAll('input,select,textarea').forEach(el=>el.addEventListener('invalid',()=>el.classList.add('is-invalid')));
const pistonDiameter=document.querySelector('[name="diameter_piston"]'),pistonArea=document.querySelector('[name="luas_piston"]');
if(pistonDiameter&&pistonArea){pistonDiameter.addEventListener('input',()=>{const d=Number(pistonDiameter.value);if(d>0)pistonArea.value=(Math.PI*d*d/4).toFixed(4)})}
