const test=require('node:test'),assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require.resolve('../dist/app.js'),'utf8'),line=source.split('\n').find(l=>l.startsWith('function bankAccountGuide('));
const ctx={esc:v=>String(v),homeDate:v=>v};vm.createContext(ctx);vm.runInContext(line,ctx);
const settings={bankAccount:{bankName:'架空銀行',accountNumber:'0000000'},registration:'2028-07-31',payment:'2028-08-31',change:'2028-09-30',cancel:'2028-09-25'};
test('未入金は振込先と期限を一回だけ表示、入金済みは変更・キャンセル期限のみ',()=>{const waiting=ctx.bankAccountGuide(settings,true,true),paid=ctx.bankAccountGuide(settings,true,false);assert.match(waiting,/架空銀行/);for(const label of ['申込期限','入金期限（振込期限）','内容変更期限','キャンセル期限'])assert.equal(waiting.split('<dt>'+label+'</dt>').length-1,1);assert.doesNotMatch(paid,/架空銀行|口座番号|入金期限|申込期限/);for(const label of ['内容変更期限','キャンセル期限'])assert.equal(paid.split('<dt>'+label+'</dt>').length-1,1);});
