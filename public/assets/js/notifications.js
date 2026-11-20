document.getElementById('markAllRead')?.addEventListener('click', async (e)=>{
  e.preventDefault();
  const fd=new FormData(); fd.append('csrf_token', window.CSRF_TOKEN);
  const res=await fetch('/api/notifications/mark_read.php',{method:'POST', body:fd, headers:{'X-CSRF-TOKEN':window.CSRF_TOKEN}});
  const j=await res.json();
  if(j.success) location.reload();
});
