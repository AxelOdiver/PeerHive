const {test} = require('node:test');
const assert = require('node:assert/strict');

async function setup(height = 1000) {
  const {initPolicyAcceptance} = await import('../../resources/js/pages/policy-acceptance.js');
  function element() {
    const target = new EventTarget();
    const classes = new Set();
    return Object.assign(target, {checked:false, disabled:false, textContent:'', scrollTop:0, clientHeight:200, scrollHeight:height, focus(){}, classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c),toggle(c,on){on?classes.add(c):classes.delete(c);}}});
  }
  const ids = Object.fromEntries(['accept_policies','policyAcceptanceModal','policyScrollContent','acceptPoliciesBtn','policyScrollHint','reviewTermsLink','reviewPrivacyLink'].map(id=>[id,element()]));
  let showCount=0,hideCount=0;
  const modal=ids.policyAcceptanceModal;
  const dialog={show(){showCount++;modal.dispatchEvent(new Event('shown.bs.modal'));},hide(){hideCount++;modal.dispatchEvent(new Event('hidden.bs.modal'));}};
  initPolicyAcceptance({getElementById:id=>ids[id],querySelector:()=>({textContent:''}),defaultView:new EventTarget()}, {getOrCreateInstance:()=>dialog});
  const click=id=>ids[id].dispatchEvent(new Event('click',{cancelable:true}));
  return {ids,click,close:()=>dialog.hide(),counts:()=>[showCount,hideCount]};
}

test('opening requires scrolling; Accept checks consent only after reaching the end',async()=>{
  const {ids,click}=await setup();
  click('accept_policies');
  assert.equal(ids.accept_policies.checked,false);
  assert.equal(ids.acceptPoliciesBtn.disabled,true);
  click('acceptPoliciesBtn');assert.equal(ids.accept_policies.checked,false);
  ids.policyScrollContent.scrollTop=400;
  ids.policyScrollContent.dispatchEvent(new Event('scroll'));
  assert.equal(ids.acceptPoliciesBtn.disabled,true);
  ids.policyScrollContent.scrollTop=800;
  ids.policyScrollContent.dispatchEvent(new Event('scroll'));
  assert.equal(ids.acceptPoliciesBtn.disabled,false);
  assert.equal(ids.acceptPoliciesBtn.classList.contains('btn-primary'),true);
  click('acceptPoliciesBtn');assert.equal(ids.accept_policies.checked,true);
});

test('closing does not accept; reopening starts at the top and requires scrolling again',async()=>{
  const {ids,click,close}=await setup();
  click('reviewTermsLink');
  ids.policyScrollContent.scrollTop=800;ids.policyScrollContent.dispatchEvent(new Event('scroll'));
  close();assert.equal(ids.accept_policies.checked,false);
  click('reviewPrivacyLink');
  assert.equal(ids.policyScrollContent.scrollTop,0);
  assert.equal(ids.acceptPoliciesBtn.disabled,true);
});

test('content fitting without scrolling can be accepted; zero-height hidden content cannot',async()=>{
  const {ids,click}=await setup(200);
  click('reviewTermsLink');assert.equal(ids.acceptPoliciesBtn.disabled,false);
  const {hasReachedPolicyEnd}=await import('../../resources/js/pages/policy-acceptance.js');
  assert.equal(hasReachedPolicyEnd({clientHeight:0,scrollTop:0,scrollHeight:0}),false);
});

test('reviewing either highlighted link preserves acceptance when the popup is closed',async()=>{
  const {ids,click,close}=await setup();
  click('accept_policies');
  ids.policyScrollContent.scrollTop=800;ids.policyScrollContent.dispatchEvent(new Event('scroll'));
  click('acceptPoliciesBtn');
  for(const link of ['reviewTermsLink','reviewPrivacyLink']) {
    click(link);
    assert.equal(ids.accept_policies.checked,true);
    assert.equal(ids.acceptPoliciesBtn.disabled,true);
    close();
    assert.equal(ids.accept_policies.checked,true);
  }
});
