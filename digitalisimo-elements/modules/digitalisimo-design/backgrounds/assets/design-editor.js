/* DIGITALÍSIMO Elements F7a: vista previa de degradados (F) y SVG de Medios (G) en el editor. */
(function(w){
	var C=w.digitalisimoDesignConfig||{},PRE=C.prefixes||{},MAX=C.max||8,POS=C.positions||[],DEF=C.defaults||{},RESP=C.responsive||[],DENY=C.deny||[],cache={};
	var get=(s,k,d)=>({}).hasOwnProperty.call(s,k)?s[k]:d;
	// Css::number, expression, length, color, global_color
	function num(v){
		if('number'!==typeof v&&('string'!==typeof v||!/^\s*[+-]?(\d+(\.\d*)?|\.\d+)(e[+-]?\d+)?\s*$/i.test(v)))return null;
		var f=Number(v),t;
		if(!isFinite(f)||Math.abs(f)>1e9)return null;
		t=Math.floor(f)===f?String(Math.trunc(f)):f.toFixed(6).replace(/0+$/,'').replace(/\.$/,'');
		return '-0'===t?'0':t;
	}
	function expr(v){
		var m,re=/([A-Za-z-]+)\s*\(/g;
		v=v.trim();
		if(!v||v.length>200||!/^[A-Za-z0-9 #%.,()+\-_*/]*$/.test(v)||v.indexOf('//')>-1)return null;
		while((m=re.exec(v)))if(['calc','min','max','clamp','var'].indexOf(m[1].toLowerCase())<0)return null;
		return v;
	}
	function len(v,u){
		if(!v||'object'!==typeof v)return null;
		var s=null==v.size?'':v.size,n;
		if(''===s||u.indexOf('string'===typeof v.unit?v.unit:'')<0)return null;
		if('custom'===v.unit)return 'string'===typeof s?expr(s):('number'===typeof s?num(s):null);
		n=num(s);
		return null===n?null:n+v.unit;
	}
	function color(r){
		if('string'!==typeof r)return null;
		var v=r.trim(),n='[-+]?(?:\\d+\\.?\\d*|\\.\\d+)',p=n+'(?:%|deg|rad|turn)?',f=(a,b)=>new RegExp('^(?:rgba?|hsla?)\\(\\s*'+p+a+p+a+p+'(?:'+b+n+'%?)?\\s*\\)$','i').test(v);
		if(!v||v.length>64)return null;
		if(/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(v)||/^var\(--e-global-color-[a-z0-9]+\)$/.test(v))return v;
		if(/^(?:transparent|currentcolor)$/i.test(v))return 'transparent'===v.toLowerCase()?'transparent':'currentColor';
		if(f('\\s*,\\s*','\\s*,\\s*')||f('\\s+','\\s*\\/\\s*'))return v;
		return null;
	}
	function glob(o,k){
		var g=o?.__globals__?.[k],m='string'===typeof g&&/^globals\/colors\?id=([a-z0-9]+)$/.exec(g);
		return m?'var(--e-global-color-'+m[1]+')':null;
	}
	// F: Gradient::build, rules
	var has=v=>v&&'object'===typeof v?null!=v.size&&''!==v.size:null!=v&&''!==v;
	var pos=v=>POS.indexOf(v)>-1?v:'center center';
	var rows=(s,p)=>{var r=get(s,p+'digi_stops',[]);return Array.isArray(r)?r.slice(0,MAX):[];};
	function at(s,k,d,l,fb){
		var i=l.indexOf(d),c=[],j,n;
		if('desktop'!==d&&i>-1){c.push(d);while(i>0&&['widescreen','desktop'].indexOf(l[i-1])<0)c.push(l[--i]);}
		c.push('desktop');
		for(j=0;j<c.length;j++){n='desktop'===c[j]?k:k+'_'+c[j];if(has(s[n]))return s[n];}
		return fb;
	}
	function edge(s,p,ck,sk,d,l){
		var c=glob(s,p+ck),r,st;
		if(null===c){r=get(s,p+ck,DEF[ck]);c=null==r||('string'===typeof r&&''===r.trim())?'transparent':color(r);}
		if(null===c)return null;
		st=len(at(s,p+sk,d,l,DEF[sk]),['%','custom']);
		return null===st?c:c+' '+st;
	}
	function stops(s,p,d,l){
		var o=[];
		rows(s,p).forEach((r,i)=>{
			if(!r||'object'!==typeof r)return;
			var c=glob(r,'color'),raw=at(r,'position',d,l,null),q=len(raw,['%']);
			if(null===c)c=color(r.color);
			if(null!==c&&null!==q)o.push([Number(raw.size),i,c+' '+q]);
		});
		return o.sort((a,b)=>a[0]===b[0]?a[1]-b[1]:(a[0]<b[0]?-1:1)).map(x=>x[2]);
	}
	function build(s,p,d,l){
		d=d||'desktop';
		if('gradient'!==get(s,p+'background',''))return null;
		var cn='conic'===get(s,p+'digi_kind',''),rp='yes'===get(s,p+'digi_repeat',''),m=stops(s,p,d,l),a,b,h,k,x;
		if(!m.length&&!cn&&!rp)return null;
		a=edge(s,p,'color','color_stop',d,l);
		b=edge(s,p,'color_b','color_b_stop',d,l);
		if(null===a||null===b)return null;
		if(cn){
			x=len(at(s,p+'digi_conic_from',d,l,{unit:'deg',size:0}),['deg']);
			h='from '+(null===x?'0deg':x)+' at '+pos(get(s,p+'digi_conic_at','center center'));
			k='conic';
		}else if('radial'===get(s,p+'gradient_type','linear')){
			h='at '+pos(at(s,p+'gradient_position',d,l,'center center'));
			k='radial';
		}else{
			x=len(at(s,p+'gradient_angle',d,l,DEF.gradient_angle),['deg','grad','rad','turn','custom']);
			h=null===x?'180deg':x;
			k='linear';
		}
		return (rp?'repeating-':'')+k+'-gradient('+h+', '+[a].concat(m,[b]).join(', ')+')';
	}
	function rules(s,p,l){
		var o={},top=build(s,p,'desktop',l);
		if(null===top)return o;
		o.desktop=top;
		l.forEach(d=>{
			var v='desktop'!==d&&(RESP.some(k=>has(s[p+k+'_'+d]))||rows(s,p).some(r=>r&&has(r['position_'+d])))?build(s,p,d,l):null;
			if(null!==v)o[d]=v;
		});
		return o;
	}
	function devices(bp){
		var k=Object.keys(bp);
		return ['desktop'].concat(k.filter(d=>'min'===bp[d].direction),k.filter(d=>'min'!==bp[d].direction).sort((a,b)=>bp[b].value-bp[a].value));
	}
	var sel=(x,c)=>c&&c.selectors?Object.keys(c.selectors).map(k=>k.split('{{WRAPPER}}').join(x.wrap).split('{{ID}}').join(x.id)).join(','):'';
	function gradient(x){
		var o='';
		(PRE[x.type]||[]).forEach(p=>{
			var r=rules(x.s,p,x.list),q=sel(x,x.controls[p+'gradient_angle']);
			if(q)Object.keys(r).forEach(d=>{
				var b=x.bp[d],css=q+'{background-color:transparent;background-image:'+r[d]+'}';
				o+='desktop'===d||!b?css:'@media('+('min'===b.direction?'min':'max')+'-width:'+b.value+'px){'+css+'}';
			});
		});
		return o;
	}
	// G: Css::svg_rejected, svg_data_uri
	function bad(t){
		var l=t.toLowerCase(),m,to,re=/(?:^|[\s"'/])(?:xlink:)?href\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/gi;
		if(l.indexOf('\0')>-1||DENY.some(d=>l.indexOf(d)>-1)||/[\s"'/]on[a-z]+\s*=/i.test(t))return true;
		while((m=re.exec(t))){to=((m[1]||'')+(m[2]||'')+(m[3]||'')).trim();if(!to||'#'!==to[0])return true;}
		return false;
	}
	function svgUri(t){
		if('string'!==typeof t||!t.trim()||t.length>C.svgRawMax||bad(t))return null;
		t=t.replace(/^\s*<\?xml[^>]*\?>/i,'').replace(/<!--[\s\S]*?-->/g,'').replace(/>\s+</g,'><').replace(/\s+/g,' ').trim();
		if(0!==t.indexOf('<svg')||t.length>C.svgMax||bad(t))return null;
		if(t.indexOf("'")<0)t=t.replace(/"/g,"'");
		return 'url("data:image/svg+xml,'+encodeURIComponent(t).replace(/[!'()*]/g,c=>'%'+c.charCodeAt(0).toString(16).toUpperCase())+'")';
	}
	function load(url,view){
		var e=cache[url],u=null;
		if(undefined===e){
			try{u=new URL(url,w.location.href);}catch(x){}
			if(!u||u.origin!==w.location.origin||!w.fetch){cache[url]=false;return null;}
			e=cache[url]={views:[view]};
			w.fetch(u.href,{credentials:'same-origin'}).then(r=>r.ok?r.text():'').then(t=>{
				cache[url]=svgUri(t)||false;
				if(!cache[url]&&C.svgError)w.elementor?.notifications?.showToast?.({message:C.svgError});
				e.views.forEach(v=>{try{v.renderStyles();}catch(x){}});
			},()=>{cache[url]=false;});
			return null;
		}
		if(e&&e.views){if(e.views.indexOf(view)<0)e.views.push(view);return null;}
		return e||null;
	}
	function svg(x,view){
		var o='';
		[['mask','shape'],['pattern','tile']].forEach(m=>{
			var n='digitalisimo_'+m[0],f=x.s[n+'_media'],q=sel(x,x.controls[n+'_'+m[1]]),u;
			if('yes'===x.s[n]&&'media'===x.s[n+'_'+m[1]]&&f&&f.url&&q&&(u=load(f.url,view)))o+=q+'{-webkit-mask-image:'+u+';mask-image:'+u+'}';
		});
		return o;
	}
	// Filtro único: selector (0,3,0) y @media
	function styleText(css,view){
		try{
			var m=view&&(view.getEditModel?view.getEditModel():view.model),st=m?.get?.('settings'),e=w.elementor,a=st?.attributes||{},t=m?.get('elType'),
				bp=(w.elementorFrontend?.config?.responsive||e?.config?.responsive||{}).activeBreakpoints||{},x;
			// Sin nada de F7a no copia los ajustes.
			if(!st||!e?.config?.document||!((PRE[t]||[]).some(p=>'gradient'===a[p+'background'])||'yes'===a.digitalisimo_mask||'yes'===a.digitalisimo_pattern))return css;
			x={type:t,id:m.get('id'),s:st.toJSON?st.toJSON():st.attributes,controls:st.controls||{},bp:bp,list:devices(bp),
				wrap:'.elementor-'+e.config.document.id+' .elementor-element.elementor-element-'+m.get('id')};
			return css+gradient(x)+svg(x,view);
		}catch(err){return css;}
	}
	var on=false,boot=()=>{if(!on&&w.elementor?.hooks){on=true;w.elementor.hooks.addFilter('editor/style/styleText',styleText);}};
	w.digitalisimoDesign={build:build,rules:rules,devices:devices,color:color,length:len,svgUri:svgUri,styleText:styleText};
	if(w.elementor?.hooks)boot();else if(w.addEventListener)w.addEventListener('elementor/init',boot);
}(window));
