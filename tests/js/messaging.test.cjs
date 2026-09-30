const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const source=fs.readFileSync('resources/js/pages/messages.js','utf8');
test('last seen uses relative units',()=>{
 const start=source.indexOf('  function presenceLabel(');
 const end=source.indexOf('  function renderActivity()',start);
 const context={Date};vm.createContext(context);vm.runInContext(source.slice(start,end),context);
 for(const [seconds,label] of [[5,'5 seconds'],[60,'1 minute'],[120,'2 minutes'],[3600,'1 hour'],[86400,'1 day'],[1209600,'2 weeks']]) {
  assert.equal(context.presenceLabel({last_seen_at:new Date(Date.now()-seconds*1000).toISOString()}),'Last seen '+label+' ago');
 }
 assert.equal(context.presenceLabel({is_online:true}),'Online');
});
test('rapid Enter consumes one draft, queues new drafts and retries with the same ID',()=>{
 let submit;const requests=[];const values=new Map();const handlers=new Map();
 function element(key){if(!values.has(key))values.set(key,'');return {
  val(v){if(v===undefined)return values.get(key);values.set(key,v);return this;},
  on(event,selector,fn){if(key==='form')submit=selector;else handlers.set(selector,fn);return this;},
  attr(){return 'token';},hide(){return this;},empty(){return this;},trigger(){return this;},
  children(){return this;},remove(){return this;},find(){return this;},append(){return this;},length:0
 };}
 const $=key=>element(key);$.ajax=options=>requests.push(options);
 const context={$,document:{},FormData,globalThis:{crypto:require('node:crypto').webcrypto},window:{},
  outgoing:[],currentConversationId:'1',currentIsGroup:false,selectedFile:null,viewingHistory:false,
  $messageForm:element('form'),$messageInput:element('input'),$activeConversationId:element('conversation'),
  $attachmentInput:element('file'),$attachmentPreview:element('preview'),$chatMessages:element('messages'),
  stopTyping(){},scrollToBottom(){},messageHtml(){return '';},refreshConversationsList(){},renderActivity(){},scheduleRead(){}};
 vm.createContext(context);
 let code=source.slice(source.indexOf('  function newSendId()'),source.indexOf("  $(document).on('click', '.start-chat-btn'"));
 // DOM rendering is separate from the submission/queue behavior under test.
 const a=code.indexOf('  function renderOutgoing()');const b=code.indexOf('  function sendNext(',a);
 code=code.slice(0,a)+'function renderOutgoing() {}\n'+code.slice(b);
 vm.runInContext(code,context);
 values.set('conversation','1');values.set('input','First');
 const send=()=>submit({preventDefault(){}});
 send();for(let i=0;i<20;i++)send();
 assert.equal(requests.length,1);assert.equal(context.outgoing.length,1);assert.equal(values.get('input'),'');
 values.set('input','Second');send();assert.equal(context.outgoing.length,2);assert.equal(requests.length,1);
 values.set('input','Still typing');requests[0].success({message:{id:1}});requests[0].complete();
 assert.equal(requests.length,2);assert.equal(requests[1].data.get('body'),'Second');assert.equal(values.get('input'),'Still typing');
 const id=requests[1].data.get('client_message_id');requests[1].error({});requests[1].complete();
 assert.equal(requests.length,2);context.outgoing[0].failed=false;context.sendNext('1');
 assert.equal(requests[2].data.get('client_message_id'),id);
 assert.notEqual(requests[0].data.get('client_message_id'),id);
});
test('pending messages render as bubbles and disappear when polling confirms their send ID',()=>{
 const appended=[];let confirmed=false;
 const chat={find(){return {length:confirmed?1:0,remove(){}};},children(){return {remove(){}};},append(html){appended.push(html);}};
 const context={$chatMessages:chat,outgoing:[{id:'draft-1',conversationId:'1',body:'<Hello>',sending:true}],currentConversationId:'1',viewingHistory:false,escapeHtml:s=>String(s).replaceAll('<','&lt;').replaceAll('>','&gt;')};
 vm.createContext(context);vm.runInContext(source.slice(source.indexOf('  function renderOutgoing()'),source.indexOf('  function sendNext(')),context);
 context.renderOutgoing();assert.equal(appended.length,1);assert.match(appended[0],/message-bubble/);assert.match(appended[0],/&lt;Hello&gt;/);assert.match(appended[0],/aria-label="Sending"/);assert.doesNotMatch(appended[0],/Waiting/);
 appended.length=0;confirmed=true;context.renderOutgoing();assert.equal(appended.length,0);
 confirmed=false;context.outgoing[0].failed=true;context.renderOutgoing();assert.match(appended[0],/Not sent · Retry/);
});
