const test=require('node:test'),assert=require('node:assert/strict'),A=require('../dist/form-assist.js');
test('参加者パスワードは文字種を問わず3文字以上、2文字以下は指定メッセージ',()=>{
 for(const pw of ['123','abc','あいう','!@#','あa1','😀😃😄','111111'])assert.equal(A.registrationPassword(pw),pw);
 for(const pw of ['','1','12','あい','😀😃'])assert.throws(()=>A.registrationPassword(pw),{message:'3桁以上のパスワードを設定してください'});
 assert.throws(()=>A.registrationPassword('a'.repeat(73)),/72バイト/);assert.throws(()=>A.registrationPassword('ab\0c'),/使用できない文字/);
});
