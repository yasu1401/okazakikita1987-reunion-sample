/* Shows the selected class's public class-organizer names beneath an inquiry class selector. */
(function(){'use strict';
function update(select){
 const form=select.closest('form'),hint=form?.querySelector('[data-class-contact-hint]');if(!hint)return;
 const value=select.value,contacts=JSON.parse(hint.dataset.classContacts||'{}');
 if(value===''){hint.textContent='クラスを選ぶと、担当するクラス幹事が表示されます。';return;}
 if(value==='0'){hint.textContent='クラスを忘れた場合は、幹事が内容を確認します。';return;}
 const names=contacts[value]||[];
 if(!names.length){hint.textContent='3年'+value+'組のクラス幹事は、まだ設定されていません。';return;}
 hint.textContent='3年'+value+'組のクラス幹事：'+names.join('、');
}
document.addEventListener('change',event=>{const select=event.target.closest('select[data-class-contact-selector]');if(select)update(select);});
document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('select[data-class-contact-selector]').forEach(update));
})();
