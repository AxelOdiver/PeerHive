const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');

test('unverified learners can send help requests, but skill swaps require their own approved subject',async()=>{
  const elements=new Map(),handlers=new Map(),sent=[];
  function element(key) {
    if(!elements.has(key))elements.set(key,{value:'',props:{},label:'',options:[],
      val(v){if(v===undefined)return this.value;this.value=v;return this;},
      prop(k,v){if(v===undefined)return this.props[k];this.props[k]=v;return this;},
      text(v){if(v===undefined)return this.label;this.label=v;return this;},
      empty(){this.options=[];return this;},append(v){this.options.push(v);return this;},
      data(){return 42;},serialize(){return 'request_type=learning&requested_subject=Coding';},
      attr(){return 'csrf-token';},
      on(event,selector,callback){handlers.set(event+selector,callback);return this;}});
    return elements.get(key);
  }
  const form={reset(){element('#learningRequestType').value='learning';},reportValidity(){return true;}};
  element('#swapRequestForm')[0]=form;
  const $=key=>key===form?element('#swapRequestForm'):element(key);
  $.ajax=options=>sent.push(options);
  const context={$,document:{},window:{showModal(){},hideModal(){},location:{assign(){}}},fetch:async()=>({ok:true,json:async()=>({peer_name:'Tutor',peer_subjects:['Coding'],my_subjects:[]})}),Option:function(text,value){this.text=text;this.value=value;},encodeURIComponent};
  vm.createContext(context);
  vm.runInContext(fs.readFileSync('resources/js/pages/learning-request.js','utf8').replace('export function','function'),context);
  handlers.get('click.open-swap-modal').call(element('trigger'));
  await new Promise(resolve=>setImmediate(resolve));
  assert.equal(element('#sendSwapRequestBtn').props.disabled,false);
  assert.equal(element('#offeredSubject').props.disabled,true);
  element('#learningRequestType').value='skill_swap';handlers.get('change#learningRequestType')();
  assert.equal(element('#sendSwapRequestBtn').props.disabled,true);
  handlers.get('submit#swapRequestForm').call(form,{preventDefault(){}});
  assert.equal(sent.length,0);
  element('#learningRequestType').value='learning';handlers.get('change#learningRequestType')();
  handlers.get('submit#swapRequestForm').call(form,{preventDefault(){}});
  assert.equal(sent.length,1);
  assert.match(sent[0].data,/request_type=learning/);
  // A second click while the first send is pending cannot duplicate the request.
  handlers.get('submit#swapRequestForm').call(form,{preventDefault(){}});assert.equal(sent.length,1);
  sent[0].error({responseJSON:{errors:{requested_subject:['Approval changed. Choose another subject.']}}});sent[0].complete();
  assert.match(element('#learningRequestStatus').label,/Approval changed/);
});
