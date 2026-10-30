const test=require('node:test'),assert=require('node:assert/strict'),R=require('../dist/core.js');
test('クラス不明を明示して申込・集計・幹事訂正・履歴保存',()=>{
 const s=R.seed(),p={...s.people[0],class:0,email:'unknown@example.test'};
 for(const cl of ['',null,-1,11,'0x0'])assert.throws(()=>R.register(s,{...p,class:cl}));
 const n=R.register(s,p),total=R.counts(s).total;assert.equal(n.class,0);assert.equal(R.classLabel(0),'クラス確認中');assert.equal(R.counts(s).classes.reduce((a,b)=>a+b,0),total-1);
 const owner={role:'owner',name:'管理者'},revision=n.revision,amount=n.amount;
 assert.throws(()=>R.correctClass(s,{role:'participant'},n.id,5,revision,'本人確認'));
 assert.throws(()=>R.correctClass(s,owner,n.id,5,revision-1,'本人確認'));
 assert.throws(()=>R.correctClass(s,owner,n.id,5,revision,''));
 R.correctClass(s,owner,n.id,5,revision,'本人確認');assert.equal(n.class,5);assert.equal(n.amount,amount);assert.equal(n.revision,revision+1);assert.equal(R.counts(s).total,total);assert.equal(s.logs.at(-1).before.class,0);assert.equal(s.logs.at(-1).after.class,5);
});
