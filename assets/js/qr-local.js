(function(global){
  'use strict';
  const VERSION=10, SIZE=VERSION*4+17, DATA_CAP=274, ECC_LEN=18;
  const BLOCK_DATA=[68,68,69,69], ALIGN=[6,28,50], MASK=0;
  const gfExp=new Uint8Array(512), gfLog=new Uint8Array(256);
  (function(){let x=1;for(let i=0;i<255;i++){gfExp[i]=x;gfLog[x]=i;x<<=1;if(x&0x100)x^=0x11d;}for(let i=255;i<512;i++)gfExp[i]=gfExp[i-255];})();
  const mul=(a,b)=>a===0||b===0?0:gfExp[gfLog[a]+gfLog[b]];
  function polyMul(a,b){const out=new Array(a.length+b.length-1).fill(0);for(let i=0;i<a.length;i++)for(let j=0;j<b.length;j++)out[i+j]^=mul(a[i],b[j]);return out;}
  function generator(n){let g=[1];for(let i=0;i<n;i++)g=polyMul(g,[1,gfExp[i]]);return g;}
  const GEN=generator(ECC_LEN);
  function rs(data){const msg=Array.from(data).concat(new Array(ECC_LEN).fill(0));for(let i=0;i<data.length;i++){const f=msg[i];if(!f)continue;for(let j=0;j<GEN.length;j++)msg[i+j]^=mul(GEN[j],f);}return msg.slice(data.length);}
  function putBits(bits,val,len){for(let i=len-1;i>=0;i--)bits.push((val>>>i)&1);}
  function toData(text){const bytes=Array.from(new TextEncoder().encode(text));if(bytes.length>271)throw new Error('QR payload is too long.');const bits=[];putBits(bits,4,4);putBits(bits,bytes.length,16);for(const b of bytes)putBits(bits,b,8);const cap=DATA_CAP*8;for(let i=0;i<4&&bits.length<cap;i++)bits.push(0);while(bits.length%8)bits.push(0);const out=[];for(let i=0;i<bits.length;i+=8){let v=0;for(let j=0;j<8;j++)v=(v<<1)|bits[i+j];out.push(v);}let pad=true;while(out.length<DATA_CAP){out.push(pad?0xec:0x11);pad=!pad;}return out;}
  function codewords(text){const data=toData(text),blocks=[];let p=0;for(const len of BLOCK_DATA){const d=data.slice(p,p+len);p+=len;blocks.push({d,e:rs(d)});}const out=[];for(let i=0;i<69;i++)for(const b of blocks)if(i<b.d.length)out.push(b.d[i]);for(let i=0;i<ECC_LEN;i++)for(const b of blocks)out.push(b.e[i]);return out;}
  function bchDigit(x){let d=0;while(x){d++;x>>>=1;}return d;}
  function bchTypeInfo(data){let d=data<<10;const g=0x537;while(bchDigit(d)-bchDigit(g)>=0)d^=g<<(bchDigit(d)-bchDigit(g));return ((data<<10)|d)^0x5412;}
  function bchVersion(v){let d=v<<12;const g=0x1f25;while(bchDigit(d)-bchDigit(g)>=0)d^=g<<(bchDigit(d)-bchDigit(g));return (v<<12)|d;}
  function matrix(text){const m=Array.from({length:SIZE},()=>Array(SIZE).fill(null));
    function finder(row,col){for(let r=-1;r<=7;r++)for(let c=-1;c<=7;c++){const y=row+r,x=col+c;if(y<0||y>=SIZE||x<0||x>=SIZE)continue;m[y][x]=(r>=0&&r<=6&&c>=0&&c<=6&&(r===0||r===6||c===0||c===6||(r>=2&&r<=4&&c>=2&&c<=4)));}}
    finder(0,0);finder(SIZE-7,0);finder(0,SIZE-7);
    for(const row of ALIGN)for(const col of ALIGN){if(m[row][col]!==null)continue;for(let r=-2;r<=2;r++)for(let c=-2;c<=2;c++)m[row+r][col+c]=(Math.abs(r)===2||Math.abs(c)===2||(r===0&&c===0));}
    for(let i=8;i<SIZE-8;i++){if(m[i][6]===null)m[i][6]=(i%2===0);if(m[6][i]===null)m[6][i]=(i%2===0);}
    const vb=bchVersion(VERSION);for(let i=0;i<18;i++){const bit=((vb>>>i)&1)!==0;m[Math.floor(i/3)][i%3+SIZE-11]=bit;m[i%3+SIZE-11][Math.floor(i/3)]=bit;}
    const fb=bchTypeInfo((1<<3)|MASK);for(let i=0;i<15;i++){const bit=((fb>>>i)&1)!==0;if(i<6)m[i][8]=bit;else if(i<8)m[i+1][8]=bit;else m[SIZE-15+i][8]=bit;}
    for(let i=0;i<15;i++){const bit=((fb>>>i)&1)!==0;if(i<8)m[8][SIZE-i-1]=bit;else if(i<9)m[8][15-i]=bit;else m[8][15-i-1]=bit;}
    m[SIZE-8][8]=true;
    const cw=codewords(text);let bi=0,bit=7,up=true;for(let col=SIZE-1;col>0;col-=2){if(col===6)col--;for(let k=0;k<SIZE;k++){const row=up?SIZE-1-k:k;for(let c=0;c<2;c++){const x=col-c;if(m[row][x]!==null)continue;let dark=false;if(bi<cw.length)dark=((cw[bi]>>>bit)&1)===1;if(((row+x)&1)===0)dark=!dark;m[row][x]=dark;bit--;if(bit<0){bi++;bit=7;}}}up=!up;}
    return m;
  }
  function svg(text,scale=5,border=4){const m=matrix(text),n=SIZE+border*2,px=n*scale;let path='';for(let y=0;y<SIZE;y++)for(let x=0;x<SIZE;x++)if(m[y][x])path+=`M${(x+border)*scale} ${(y+border)*scale}h${scale}v${scale}h-${scale}z`;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${px} ${px}" width="${px}" height="${px}" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="${path}" fill="#000"/></svg>`;}
  function render(el,text){
    try{
      el.innerHTML=svg(text,4,4);
      el.dataset.qrRendered='1';
      el.removeAttribute('aria-busy');
    }catch(err){
      el.textContent='QR unavailable - use the setup key below.';
      el.classList.add('qr-error');
      el.removeAttribute('aria-busy');
    }
  }
  function enhance(root){
    (root||document).querySelectorAll('[data-otpauth-qr]').forEach(el=>{
      const text=el.getAttribute('data-otpauth-qr')||'';
      if(!text||el.dataset.qrRendered==='1')return;
      render(el,text);
    });
  }
  global.LocalQr={matrix,svg,render,enhance};
  if(typeof document!=='undefined'){
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>enhance(document),{once:true});
    else enhance(document);
    document.addEventListener('auraa:content-updated',event=>enhance(event.detail?.root||document));
  }
})(typeof window!=='undefined'?window:globalThis);
