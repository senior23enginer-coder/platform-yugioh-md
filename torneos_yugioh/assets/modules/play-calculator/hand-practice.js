(function(root,factory){
  if(typeof module==='object'&&module.exports)module.exports=factory(require('./engine.js'));
  else root.YgoHandPractice=factory(root.YgoPlayEngine);
})(typeof globalThis!=='undefined'?globalThis:this,function(E){
  'use strict';
  function random(){
    if(!globalThis.crypto?.getRandomValues)return Math.random();
    return globalThis.crypto.getRandomValues(new Uint32Array(1))[0]/4294967296;
  }
  function remaining(s){
    return s.self.cards.filter(c=>c.section==='main').flatMap(c=>{
      const used=s.tokens.filter(t=>t.owner==='self'&&t.key===c.key&&t.zone!=='deck').length;
      return Array.from({length:Math.max(0,c.quantity-used)},()=>c.key);
    });
  }
  function draw(s,count=1,rng=random){
    E.validate(s);
    const pool=remaining(s);
    if(!Number.isInteger(count)||count<1||pool.length<count)throw Error('No quedan suficientes cartas en tu Main Deck para ese robo.');
    let next=E.clone(s);
    for(let i=0;i<count;i++){
      const value=rng();
      if(!Number.isFinite(value)||value<0||value>=1)throw Error('No se pudo generar el robo aleatorio.');
      const index=Math.floor(value*pool.length),key=pool.splice(index,1)[0];
      const token=next.tokens.find(t=>t.owner==='self'&&t.key===key&&t.zone==='deck');
      next=E.place(next,token?{uid:token.uid,zone:'hand',controller:'self'}:{key,owner:'self',controller:'self',zone:'hand'});
    }
    return next;
  }
  function opening(s,rng=random){
    const next=s.practice?E.create(s.self):E.create(s.self,s.opponent);
    if(next.format==='rush_duel'||next.format==='goat')next.phase='draw';
    return draw(next,next.format==='rush_duel'?4:5,rng);
  }
  return {draw,opening,remaining};
});
