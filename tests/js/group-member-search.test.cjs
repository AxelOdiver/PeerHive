const {test}=require('node:test');
const assert=require('node:assert/strict');

test('selected members persist across searches, deduplicate, can be removed, and clear on close',async()=>{
  const {initGroupMemberSearch}=await import('../../resources/js/pages/group-member-search.js');
  function element() {
    const e=new EventTarget();
    const parts=new Map();
    return Object.assign(e,{children:[],value:'',textContent:'',disabled:false,focus(){},setAttribute(){},
      replaceChildren(){this.children=[];},append(child){this.children.push(child);},
      querySelector(key){if(!parts.has(key))parts.set(key,element());return parts.get(key);},
      cloneNode(){return element();}});
  }
  const ids=new Map();
  const doc={getElementById(id){if(!ids.has(id))ids.set(id,element());return ids.get(id);}};
  for(const id of ['groupMemberResultTemplate','groupSelectedMemberTemplate'])doc.getElementById(id).content={firstElementChild:element()};
  const originalFetch=global.fetch;
  const responses=[[{id:1,name:'First',username:'first'}],[{id:1,name:'First',username:'first'},{id:2,name:'Second',username:'second'}]];
  global.fetch=async()=>({ok:true,json:async()=>({users:responses.shift()})});
  const tick=()=>new Promise(resolve=>setTimeout(resolve,330));
  const click=e=>e.dispatchEvent(new Event('click'));
  const input=doc.getElementById('newGroupSearchInput');
  const results=doc.getElementById('groupMembersList');
  try {
    const api=initGroupMemberSearch(doc);
    assert.equal(results.children.length,0);
    assert.equal(doc.getElementById('createGroupBtn').disabled,true);
    input.value='first';input.dispatchEvent(new Event('input'));await tick();
    click(results.children[0]);click(results.children[0]);
    assert.deepEqual(api.ids(),[1]);
    input.value='second';input.dispatchEvent(new Event('input'));await tick();
    assert.deepEqual(api.ids(),[1]);
    click(results.children[1]);assert.deepEqual(api.ids(),[1,2]);
    assert.equal(doc.getElementById('createGroupBtn').disabled,false);
    click(doc.getElementById('groupSelectedMembers').children[0].querySelector('button'));
    assert.deepEqual(api.ids(),[2]);
    assert.equal(doc.getElementById('createGroupBtn').disabled,true);
    doc.getElementById('newGroupModal').dispatchEvent(new Event('hidden.bs.modal'));
    assert.deepEqual(api.ids(),[]);assert.equal(input.value,'');assert.equal(results.children.length,0);
    // A slower previous search must not repopulate results after the query is cleared.
    let finishSearch;
    global.fetch=()=>new Promise(resolve=>{finishSearch=resolve;});
    input.value='third';input.dispatchEvent(new Event('input'));await tick();
    input.value='t';input.dispatchEvent(new Event('input'));
    finishSearch({ok:true,json:async()=>({users:[{id:3,name:'Stale',username:'third'}]})});
    await new Promise(resolve=>setImmediate(resolve));
    assert.equal(results.children.length,0);
    api.clear();
  } finally {global.fetch=originalFetch;}
});
