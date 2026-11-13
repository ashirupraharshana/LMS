document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('input[name="username"]').forEach(field=>{
    const hint=field.parentElement.querySelector('.username-hint');
    let timer=0;let serial=0;
    field.addEventListener('input',()=>{
      clearTimeout(timer);const s=++serial;
      const value=field.value.trim();
      if(!value){if(hint)hint.textContent='Enter a username to check availability.';return;}
      if(!/^[a-z][a-z0-9._-]{2,31}$/i.test(value)){
        if(hint)hint.textContent='3–32 characters; start with a letter.';
        return;
      }
      if(hint)hint.textContent='Checking availability…';
      timer=setTimeout(async()=>{
        try{
          const qs=new URLSearchParams({username:value,exclude:field.dataset.exclude||''});
          const res=await fetch('check_username.php?'+qs,{credentials:'same-origin',headers:{Accept:'application/json'}});
          if(!res.ok)throw new Error('Request failed');
          const data=await res.json();
          if(serial===s && hint)hint.textContent=data.message;
        }catch{if(serial===s && hint)hint.textContent='Availability check unavailable; username will be checked when saving.';}
      },320);
    });
  });
});
