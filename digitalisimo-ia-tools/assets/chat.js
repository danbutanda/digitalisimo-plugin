/* Chatbot público: la pregunta y la respuesta del modelo se pintan siempre como texto (nodos y textContent), nunca como HTML. */
(function(){'use strict';
function add(log,who,text){var p=document.createElement('p'),b=document.createElement('strong');b.textContent=who+':';p.appendChild(b);String(text).replace(/\r\n?/g,'\n').split('\n').forEach(function(line,n){if(n)p.appendChild(document.createElement('br'));p.appendChild(document.createTextNode((n?'':' ')+line))});log.appendChild(p)}
document.addEventListener('submit',async function(e){var f=e.target;if(!f.closest||!f.closest('.digitalisimo-chatbot'))return;e.preventDefault();var i=f.querySelector('input'),log=f.parentNode.querySelector('.digitalisimo-chatbot-log'),m=i?i.value.trim():'',a='';if(!m||!log)return;add(log,'Tú',m);i.value='';
try{var r=await fetch(digitalisimoChatbot.url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:m})}),d=await r.json();a=d&&(d.answer||d.message)}catch(x){}
add(log,'Digitalisimo',typeof a==='string'&&a.trim()?a:'No fue posible responder.')});})();
