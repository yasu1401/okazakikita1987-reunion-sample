const test=require('node:test'),assert=require('node:assert/strict');
const A=require('../dist/form-assist.js'),R=require('../dist/core.js');
test('郵便番号は7桁で整形・全角とハイフンを扱い、空欄を保持',()=>{
 assert.equal(A.formatPostal('４４４００７５'),'444-0075');
 assert.equal(A.postalDigits('444-0075'),'4440075');
 assert.equal(A.formatPostal(''),'');assert.equal(A.formatPostal('4440'),'4440');
});
test('住所検索は郵便番号だけを送信し、複数候補・重複・別コードを区別',async()=>{
 let sent;
 const rows=[{zipcode:'4440075',address1:'愛知県',address2:'岡崎市',address3:'伊賀町'},{zipcode:'4440075',address1:'愛知県',address2:'岡崎市',address3:'伊賀町'},{zipcode:'4440075',address1:'愛知県',address2:'岡崎市',address3:'別の町'},{zipcode:'0000000',address1:'対象外'}];
 const found=await A.postalAddresses('4440075',async(url,options)=>{sent={url,options};return {ok:true,json:async()=>({status:200,results:rows})};});
 assert.deepEqual(found,['愛知県岡崎市伊賀町','愛知県岡崎市別の町']);
 assert.equal(sent.url,'https://zipcloud.ibsnet.co.jp/api/search?zipcode=4440075&limit=100');assert.equal(sent.options.credentials,'omit');assert.equal(sent.options.referrerPolicy,'no-referrer');
});
test('検索該当なし・APIエラー・通信失敗・不正な桁数',async()=>{
 assert.deepEqual(await A.postalAddresses('0000000',async()=>({ok:true,json:async()=>({status:200,results:null})})),[]);
 await assert.rejects(A.postalAddresses('4440075',async()=>({ok:false})),/通信/);
 await assert.rejects(A.postalAddresses('4440075',async()=>({ok:true,json:async()=>({status:400})})),/検索/);
 await assert.rejects(A.postalAddresses('4440075',async()=>{throw new Error('offline')}),/offline/);
 await assert.rejects(A.postalAddresses('444',async()=>{assert.fail('送信不可')}),/7桁/);
});
test('郵便番号・住所が空でも申込可能、電話番号はハイフンなしを保存',()=>{
 const s=R.seed();const p=R.register(s,{class:1,name:'山田 花子',kana:'やまだ はなこ',email:'postal-test@example.test',postal:'',address:'',phone:'09012345678',payer:'ヤマダ ハナコ',display:'山田 花子',public:true});
 assert.equal(p.address,'');assert.equal(p.postal,'');assert.equal(p.phone,'09012345678');
});
