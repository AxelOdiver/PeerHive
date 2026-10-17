@extends('layouts.dashboard')
@section('title', 'Learning Hub Preview')
@push('styles')
<style>
#learningPreview [hidden]{display:none!important}
#learningPreview .preview-tabs{gap:.5rem;margin-bottom:1.5rem}
#learningPreview .preview-tabs .nav-link{color:var(--bs-body-color);border:1px solid var(--bs-border-color);border-radius:.5rem;background:var(--bs-body-bg)}
#learningPreview .preview-tabs .nav-link[aria-current=page]{background:var(--bs-primary);border-color:var(--bs-primary);color:white}
#learningPreview .notice{border-left:3px solid var(--bs-warning);background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis);padding:.75rem 1rem;margin-bottom:1.5rem;border-radius:.5rem;font-size:.85rem}
#learningPreview .heading{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem}
#learningPreview h1{font-size:1.8rem;font-weight:700;margin:0 0 .5rem}
#learningPreview h2{font-size:1.15rem;font-weight:700;margin:0}
#learningPreview h3{font-size:1.05rem;font-weight:700;margin:0 0 .5rem}
#learningPreview .muted{color:var(--bs-secondary-color);font-size:.875rem;margin:0}
#learningPreview .eyebrow{font-size:.72rem;color:var(--bs-primary);text-transform:uppercase;letter-spacing:.08em;font-weight:700;margin:0 0 .5rem}
#learningPreview .card{padding:1.5rem;background:var(--bs-body-bg);color:var(--bs-body-color);margin-bottom:0}
#learningPreview .btn{white-space:normal}
#learningPreview .metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin-bottom:1.5rem}
#learningPreview .metric{padding:1.25rem}
#learningPreview .metric-label{color:var(--bs-secondary-color);font-size:.8rem;display:flex;justify-content:space-between;gap:.5rem}
#learningPreview .metric-icon{color:var(--bs-primary);background:var(--bs-primary-bg-subtle);border-radius:.5rem;padding:.1rem .4rem}
#learningPreview .metric strong{font-size:2rem;display:block;margin:.65rem 0 .3rem;font-weight:700}
#learningPreview .metric small{font-size:.75rem;color:var(--bs-secondary-color)}
#learningPreview .grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(0,1fr);gap:1.5rem}
#learningPreview .card-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap}
#learningPreview .select{border:1px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-body-color);padding:.4rem .6rem;border-radius:.5rem;font-size:.8rem;max-width:100%}
#learningPreview .chart{height:180px;display:flex;gap:1rem;align-items:end;padding:1rem .5rem 0;background:repeating-linear-gradient(to top,var(--bs-border-color-translucent) 0,var(--bs-border-color-translucent) 1px,transparent 1px,transparent 40px)}
#learningPreview .bar-group{height:100%;flex:1;display:flex;align-items:end;justify-content:center;gap:4px}
#learningPreview .bar{width:20px;background:var(--bs-primary);border-radius:4px 4px 0 0}
#learningPreview .bar.light{background:var(--bs-info)}
#learningPreview .days{display:flex;justify-content:space-around;font-size:.75rem;color:var(--bs-secondary-color);padding:.6rem 0}
#learningPreview .legend{display:flex;gap:1rem;font-size:.75rem;color:var(--bs-secondary-color)}
#learningPreview .dot{display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:5px;background:var(--bs-primary)}
#learningPreview .dot.light{background:var(--bs-info)}
#learningPreview .goal{padding:1rem 0;border-bottom:1px solid var(--bs-border-color)}
#learningPreview .goal:last-child{border:0;padding-bottom:0}
#learningPreview .goal-row{display:flex;justify-content:space-between;gap:.75rem;font-size:.8rem;margin-bottom:.6rem}
#learningPreview .track{height:7px;background:var(--bs-tertiary-bg);border-radius:1rem;overflow:hidden}
#learningPreview .fill{height:100%;background:var(--bs-primary);border-radius:1rem}
#learningPreview .goal small{display:block;color:var(--bs-secondary-color);font-size:.75rem;margin-top:.5rem}
#learningPreview .list{margin-top:1.5rem}
#learningPreview .activity{display:flex;gap:1rem;align-items:center;padding:1rem 0;border-bottom:1px solid var(--bs-border-color)}
#learningPreview .activity:last-child{border:0;padding-bottom:0}
#learningPreview .activity-icon{width:40px;height:40px;border-radius:.75rem;background:var(--bs-primary-bg-subtle);color:var(--bs-primary);display:grid;place-items:center;flex-shrink:0}
#learningPreview .activity-body{flex:1;min-width:0}
#learningPreview .activity-body p{font-size:.875rem;font-weight:600;margin:0}
#learningPreview .activity-body small{font-size:.75rem;color:var(--bs-secondary-color)}
#learningPreview .points{font-size:.75rem;font-weight:700;color:var(--bs-success-text-emphasis)}
#learningPreview .hero{padding:2rem;background:var(--bs-primary-bg-subtle);color:var(--bs-primary-text-emphasis);border:1px solid var(--bs-primary-border-subtle);border-radius:1rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.5rem}
#learningPreview .hero .eyebrow{color:inherit!important}
#learningPreview .hero .balance{font-size:2.75rem;font-weight:700;margin:.5rem 0}
#learningPreview .hero .balance span{color:inherit!important}
#learningPreview .hero-art{font-size:4rem;color:var(--bs-primary);padding:.5rem 1.5rem}
#learningPreview .section-head{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin:1.5rem 0 1rem}
#learningPreview .badges,#learningPreview .rewards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
#learningPreview .achievement{text-align:center;align-items:center}
#learningPreview .medal{width:60px;height:66px;margin:0 auto 1rem;display:grid;place-items:center;font-size:1.65rem;background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis);clip-path:polygon(50% 0,95% 22%,95% 73%,50% 100%,5% 73%,5% 22%)}
#learningPreview .medal.mint{background:var(--bs-success-bg-subtle);color:var(--bs-success-text-emphasis)}
#learningPreview .medal.blue{background:var(--bs-primary-bg-subtle);color:var(--bs-primary-text-emphasis)}
#learningPreview .achievement .muted{font-size:.8rem;margin:.5rem 0 1rem;min-height:2.5rem}
#learningPreview .achievement .track{width:100%}
#learningPreview .tag{display:inline-block;align-self:flex-start;border-radius:1rem;font-size:.68rem;font-weight:600;padding:.3rem .6rem;background:var(--bs-success-bg-subtle);color:var(--bs-success-text-emphasis)}
#learningPreview .achievement .tag{align-self:center}
#learningPreview .tag.soft{background:var(--bs-secondary-bg);color:var(--bs-secondary-color)}
#learningPreview .tag.gold{background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis)}
#learningPreview .reward-icon{font-size:1.75rem;margin-bottom:.75rem;color:var(--bs-primary)}
#learningPreview .reward-foot{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-top:auto;padding-top:1.25rem}
#learningPreview .reward-foot strong{font-size:.85rem;color:var(--bs-primary)}
#learningPreview .filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}
#learningPreview .filter{border:1px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-body-color);border-radius:1rem;padding:.5rem 1rem;font-size:.8rem}
#learningPreview .filter[aria-pressed=true]{background:var(--bs-primary);color:white;border-color:var(--bs-primary)}
#learningPreview .session-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem}
#learningPreview .session{padding:0;overflow:hidden}
#learningPreview .session-banner{height:90px;padding:1.25rem;display:flex;justify-content:space-between;align-items:center;background:var(--bs-primary-bg-subtle);color:var(--bs-primary-text-emphasis)}
#learningPreview .session-banner.blue{background:var(--bs-info-bg-subtle);color:var(--bs-info-text-emphasis)}
#learningPreview .session-banner.sand{background:var(--bs-warning-bg-subtle);color:var(--bs-warning-text-emphasis)}
#learningPreview .session-banner .subject{font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
#learningPreview .session-banner .symbol{font-size:2rem}
#learningPreview .session-body{padding:1.25rem;display:flex;flex-direction:column;flex:1}
#learningPreview .session h3{margin:.75rem 0;min-height:3rem;line-height:1.5}
#learningPreview .session-desc{font-size:.8rem;color:var(--bs-secondary-color);min-height:3.75rem}
#learningPreview .session-meta{font-size:.75rem;color:var(--bs-secondary-color);display:grid;gap:.5rem;margin:.75rem 0}
#learningPreview .capacity{display:flex;justify-content:space-between;gap:.5rem;font-size:.75rem;color:var(--bs-secondary-color);margin-top:auto}
#learningPreview .session-actions{display:flex;gap:.5rem;margin-top:1rem}
#learningPreview .session-actions .btn{flex:1;font-size:.8rem}
#learningPreview .session-note{background:var(--bs-secondary-bg);color:var(--bs-secondary-color);padding:1.25rem;margin-top:1.5rem;border-radius:.75rem;font-size:.8rem}
#learningPreview dialog{border:1px solid var(--bs-border-color);background:var(--bs-body-bg);color:var(--bs-body-color);border-radius:1rem;padding:1.5rem;max-width:470px;width:calc(100% - 32px);box-shadow:0 1rem 4rem #0003}
#learningPreview dialog::backdrop{background:#0008}
#learningPreview dialog p{font-size:.9rem;color:var(--bs-secondary-color)}
#learningPreview .dialog-close{float:right;border:0;background:none;color:var(--bs-body-color);font-size:1.5rem}
#learningPreview .dialog-tag{font-size:.7rem;color:var(--bs-warning-text-emphasis)}
#learningPreview .preview-toast{position:fixed;bottom:1.5rem;right:1.5rem;max-width:350px;background:var(--bs-body-color);color:var(--bs-body-bg);border-radius:.75rem;padding:1rem;z-index:1100;box-shadow:0 .5rem 2rem #0003;font-size:.8rem}
@media(max-width:1199.98px){#learningPreview .grid{grid-template-columns:1fr}#learningPreview .session-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767.98px){#learningPreview .metrics{grid-template-columns:1fr 1fr}#learningPreview .badges,#learningPreview .rewards,#learningPreview .session-grid{grid-template-columns:1fr}#learningPreview .card{padding:1.1rem}#learningPreview .session{padding:0}#learningPreview h1{font-size:1.5rem}#learningPreview .bar{width:12px}#learningPreview .chart{gap:.5rem}#learningPreview .hero{padding:1.25rem}#learningPreview .hero-art{font-size:2.5rem;padding:.5rem}#learningPreview .preview-toast{left:1rem;right:1rem}#learningPreview .session h3,#learningPreview .session-desc{min-height:0}}
</style>
@endpush
@section('content')
<div id="learningPreview">
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><h2 class="h4 mb-0 fw-bold">Learning Hub</h2><span class="badge text-bg-warning">Design preview · Sample data</span></div>
<nav class="nav nav-pills preview-tabs" aria-label="Preview features">
<button class="nav-link" data-tab="progress" type="button"> <i class="bi bi-graph-up-arrow me-1"></i> My Progress</button>
<button class="nav-link" data-tab="rewards" type="button"><i class="bi bi-gift me-1"></i> Rewards Hub</button>
<button class="nav-link" data-tab="sessions" type="button"><i class="bi bi-calendar-event me-1"></i> Study Sessions</button>
</nav>
<div class="notice"><strong>Sample data only.</strong> Explore the layout. Joining sessions and viewing rewards here does not change your account.</div>
<section id="progress" aria-labelledby="progress-title">
  <div class="heading"><div><p class="eyebrow">Your learning journey</p><h1 id="progress-title">Small steps. Real progress.</h1><p class="muted">A look at what you've learned, shared, and accomplished.</p></div><button class="btn btn-outline-secondary" data-go="sessions">Find a study session ↗</button></div>
  <div class="metrics"><div class="metric card border-0 shadow-sm rounded-4"><span class="metric-label">Sessions completed <span class="metric-icon">✓</span></span><strong>12</strong><small>8 learning · 4 teaching</small></div><div class="metric card border-0 shadow-sm rounded-4"><span class="metric-label">Learning & teaching hours <span class="metric-icon">◷</span></span><strong>18 <span style="font-size:16px;font-weight:400">hrs</span></strong><small>12 learning · 6 teaching</small></div><div class="metric card border-0 shadow-sm rounded-4"><span class="metric-label">Subjects explored <span class="metric-icon">▤</span></span><strong>3</strong><small>Coding, design & languages</small></div><div class="metric card border-0 shadow-sm rounded-4"><span class="metric-label">Recognition points <span class="metric-icon">✧</span></span><strong>360</strong><small>From confirmed session completions</small></div></div>
  <div class="grid"><article class="card border-0 shadow-sm rounded-4"><div class="card-head"><div><h2>Time well spent</h2><p class="muted">Hours by day · sample week</p></div><select class="select" id="chartMode" aria-label="Filter activity chart"><option value="both">Learning & teaching</option><option value="learning">Learning only</option><option value="teaching">Teaching only</option></select></div><div class="legend"><span><i class="dot"></i>Learning</span><span><i class="dot light"></i>Teaching</span></div><div class="chart" role="img" aria-label="Sample weekly hours: Monday 1 learning; Tuesday 2 learning and 1 teaching; Wednesday 1 learning; Thursday 1 teaching; Friday 3 learning and 1 teaching; Saturday 2 learning and 1 teaching; Sunday 3 learning and 2 teaching.">
  @foreach([[1,0],[2,1],[1,0],[0,1],[3,1],[2,1],[3,2]] as [$learning,$teaching])
    <div class="bar-group"><div class="bar" data-learning style="height:{{ $learning*27 }}px" title="Learning: {{ $learning }} hours"></div><div class="bar light" data-teaching style="height:{{ $teaching*27 }}px" title="Teaching: {{ $teaching }} hours"></div></div>
  @endforeach
  </div><div class="days"><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span></div></article>
  <article class="card border-0 shadow-sm rounded-4"><div class="card-head"><h2>Your weekly goals</h2><span class="tag">Sample goals</span></div><div class="goal"><div class="goal-row"><span>Learn with a peer</span><strong>2 / 3 sessions</strong></div><div class="track"><div class="fill" style="width:67%"></div></div><small>One more learning session to reach your goal.</small></div><div class="goal"><div class="goal-row"><span>Share your knowledge</span><strong>1 / 2 sessions</strong></div><div class="track"><div class="fill" style="width:50%"></div></div><small>For subjects you are approved to teach.</small></div><div class="goal"><div class="goal-row"><span>Make time to learn</span><strong>3 / 4 hours</strong></div><div class="track"><div class="fill" style="width:75%"></div></div><small>Build a routine that works for you.</small></div></article></div>
  <article class="card border-0 shadow-sm rounded-4 list"><div class="card-head"><h2>Recent milestones</h2><button class="btn btn-outline-secondary" data-go="rewards">View achievements →</button></div><div class="activity"><span class="activity-icon">✓</span><div class="activity-body"><p>Completed “Python loops, together”</p><small>Learning session · Coding · 1 hour</small></div><span class="points">+30 points</span></div><div class="activity"><span class="activity-icon">◇</span><div class="activity-body"><p>Earned the Learning Together badge</p><small>Completed five peer learning sessions</small></div><span class="tag gold">Achievement</span></div><div class="activity"><span class="activity-icon">↗</span><div class="activity-body"><p>Helped a peer practise conversational Spanish</p><small>Teaching session · Foreign Language · 1 hour</small></div><span class="points">+30 points</span></div></article>
</section>
<section id="rewards" hidden aria-labelledby="rewards-title"><div class="heading"><div><p class="eyebrow">Celebrate showing up</p><h1 id="rewards-title">Your effort deserves a little recognition.</h1><p class="muted">Collect achievements as you learn and help others.</p></div><button class="btn btn-outline-secondary" data-go="progress">My progress ↗</button></div><div class="hero"><div><p class="eyebrow" style="color:#a3d5c5">Your sample balance</p><div class="balance">360 <span style="font-size:16px;font-weight:400;color:#c2dad4">points</span></div><p class="muted">Earned through confirmed completed sessions.<br>Learning and teaching both count.</p></div><div class="hero-art" aria-hidden="true">✧</div></div><div class="section-head"><h2>A few things to be proud of</h2><span class="muted">Sample achievements</span></div><div class="badges"><article class="card border-0 shadow-sm rounded-4 achievement"><div class="medal">✦</div><h3>First Step</h3><p class="muted">Complete your first peer learning session.</p><span class="tag">Earned</span></article><article class="card border-0 shadow-sm rounded-4 achievement"><div class="medal mint">♧</div><h3>Learning Together</h3><p class="muted">Complete five peer learning sessions.</p><span class="tag">Earned</span></article><article class="card border-0 shadow-sm rounded-4 achievement"><div class="medal blue">↗</div><h3>Knowledge Sharer</h3><p class="muted">Complete five sessions helping others learn.</p><div class="track"><div class="fill" style="width:80%"></div></div><p class="muted" style="min-height:0;margin:7px 0 0">4 / 5 sessions</p></article></div><div class="section-head"><h2>Make your space your own</h2><span class="muted">Example cosmetic rewards</span></div><div class="rewards"><article class="card border-0 shadow-sm rounded-4"><div class="reward-icon">◉</div><h3>Forest profile frame</h3><p class="muted">A fresh green accent around your avatar.</p><div class="reward-foot"><strong>200 points</strong><button class="btn btn-outline-secondary" data-reward="Forest profile frame">Preview</button></div></article><article class="card border-0 shadow-sm rounded-4"><div class="reward-icon">✺</div><h3>Sunrise profile frame</h3><p class="muted">A warm highlight for your profile.</p><div class="reward-foot"><strong>300 points</strong><button class="btn btn-outline-secondary" data-reward="Sunrise profile frame">Preview</button></div></article><article class="card border-0 shadow-sm rounded-4"><div class="reward-icon">▱</div><h3>Learning journal cover</h3><p class="muted">A cover style for your personal progress page.</p><div class="reward-foot"><strong>400 points</strong><button class="btn btn-outline-secondary" data-reward="Learning journal cover">Preview</button></div></article></div><p class="session-note">Achievement badges celebrate participation. The separate <strong>Teaching verified</strong> badge only comes from administrator-approved subject qualifications. Points never buy verification.</p></section>
<section id="sessions" hidden aria-labelledby="sessions-title"><div class="heading"><div><p class="eyebrow">Better, together</p><h1 id="sessions-title">Find your next study circle.</h1><p class="muted">Join a community session, practise a skill, or learn from a verified tutor.</p></div><button class="btn btn-primary" id="createSession">+ Create a session</button></div><div class="filters" role="group" aria-label="Filter study sessions"><button class="filter" data-filter="all" aria-pressed="true">All sessions</button><button class="filter" data-filter="peer" aria-pressed="false">Peer study groups</button><button class="filter" data-filter="tutor" aria-pressed="false">Tutor-led</button><button class="filter" data-filter="joined" aria-pressed="false">My sessions</button></div><div class="session-grid">
@foreach([
 ['id'=>'python','type'=>'peer','subject'=>'Coding','tone'=>'','symbol'=>'{ }','title'=>'Python practice, one problem at a time','community'=>'Coding Circle','host'=>'Alex','time'=>'Monday · 4:00–5:00 PM','count'=>4,'limit'=>8,'description'=>'Work through beginner loops and functions together. Bring one problem you would like to solve.'],
 ['id'=>'design','type'=>'tutor','subject'=>'Graphic Design','tone'=>'blue','symbol'=>'◈','title'=>'Design a poster that tells a story','community'=>'Creative Studio','host'=>'Sam','time'=>'Tuesday · 3:00–4:00 PM','count'=>5,'limit'=>6,'description'=>'Explore layout, hierarchy, and colour with a tutor approved to teach Graphic Design.'],
 ['id'=>'language','type'=>'peer','subject'=>'Foreign Language','tone'=>'sand','symbol'=>'Aa','title'=>'A little Spanish, a lot of conversation','community'=>'Language Exchange','host'=>'Jamie','time'=>'Wednesday · 5:00–6:00 PM','count'=>3,'limit'=>8,'description'=>'A relaxed peer practice circle. Try everyday phrases and help each other build confidence.']
] as $session)
<article class="card border-0 shadow-sm rounded-4 session" data-session="{{ $session['id'] }}" data-type="{{ $session['type'] }}" data-joined="false"><div class="session-banner {{ $session['tone'] }}"><span class="subject">{{ $session['subject'] }}</span><span class="symbol" aria-hidden="true">{{ $session['symbol'] }}</span></div><div class="session-body"><span class="tag {{ $session['type']==='tutor' ? '' : 'soft' }}">{{ $session['type']==='tutor' ? 'Tutor-led · Teaching verified' : 'Peer study group' }}</span><h3>{{ $session['title'] }}</h3><p class="session-desc">{{ $session['description'] }}</p><div class="session-meta"><span>◷ &nbsp; {{ $session['time'] }}</span><span>▦ &nbsp; Online · Asia/Taipei</span><span>♧ &nbsp; {{ $session['community'] }} · Hosted by {{ $session['host'] }}</span></div><div class="capacity"><span><span data-count>{{ $session['count'] }}</span> / {{ $session['limit'] }} spots</span><span>Sample session</span></div><div class="track" style="margin-top:7px"><div class="fill" style="width:{{ $session['count']/$session['limit']*100 }}%"></div></div><div class="session-actions"><button class="btn btn-outline-secondary" data-details="{{ $session['title'] }}">Details</button><button class="btn btn-primary" data-join data-count-base="{{ $session['count'] }}" data-limit="{{ $session['limit'] }}">Try joining</button></div></div></article>
@endforeach
</div><p id="noSessions" class="card border-0 shadow-sm rounded-4 muted" hidden>No sample sessions match this filter. Try joining a session in All sessions first.</p><div class="session-note"><strong>Where this would live:</strong> Community → Study Sessions for discovering and organizing; Schedule → Upcoming Sessions for your bookings. Peer study organizers do not need teaching verification. Tutor-led hosts need approval for the subject.</div></section>

<dialog id="previewDialog" aria-labelledby="dialogTitle"><button class="dialog-close" id="closeDialog" aria-label="Close">×</button><span class="dialog-tag">PREVIEW ONLY</span><h2 id="dialogTitle" style="margin-top:12px"></h2><p id="dialogBody"></p><button class="btn btn-primary" id="dismissDialog">Got it</button></dialog><div class="preview-toast" id="toast" role="status" hidden></div>
<script>
const tabs=['progress','rewards','sessions'];
function showTab(tab){if(!tabs.includes(tab))tab='progress';for(const name of tabs)document.getElementById(name).hidden=name!==tab;document.querySelectorAll('[data-tab]').forEach(button=>{if(button.dataset.tab===tab)button.setAttribute('aria-current','page');else button.removeAttribute('aria-current')});document.querySelectorAll('a[href]').forEach(link=>{const target=new URL(link.href,location.origin);if(target.pathname===location.pathname&&target.searchParams.has('tab'))link.classList.toggle('active',target.searchParams.get('tab')===tab)});history.replaceState(null,'','?tab='+tab);window.scrollTo(0,0)}
document.querySelectorAll('[data-tab]').forEach(button=>button.addEventListener('click',()=>showTab(button.dataset.tab)));document.querySelectorAll('[data-go]').forEach(button=>button.addEventListener('click',()=>showTab(button.dataset.go)));showTab(new URLSearchParams(location.search).get('tab')||'progress');
document.getElementById('chartMode').addEventListener('change',event=>{document.querySelectorAll('[data-learning]').forEach(bar=>bar.hidden=event.target.value==='teaching');document.querySelectorAll('[data-teaching]').forEach(bar=>bar.hidden=event.target.value==='learning')});
const dialog=document.getElementById('previewDialog');function openDialog(title,body){document.getElementById('dialogTitle').textContent=title;document.getElementById('dialogBody').textContent=body;dialog.showModal()}document.getElementById('closeDialog').onclick=()=>dialog.close();document.getElementById('dismissDialog').onclick=()=>dialog.close();
document.querySelectorAll('[data-reward]').forEach(button=>button.onclick=()=>openDialog(button.dataset.reward,'This is an example cosmetic reward. In the finished feature, you could preview and unlock it with earned points. No points are spent in this preview.'));
document.getElementById('createSession').onclick=()=>openDialog('Create a study session','The session form would ask for a topic, community, peer-study or tutor-led format, date and timezone, capacity, and meeting method. Tutor-led sessions would check the host’s subject qualification.');document.querySelectorAll('[data-details]').forEach(button=>button.onclick=()=>openDialog(button.dataset.details,'Sample agenda: 10 minutes to introduce the topic, 35 minutes of guided or peer practice, and 15 minutes for questions and next steps. Meeting details would be available to registered members.'));
let filter='all',toastTimer;function applyFilter(){let count=0;document.querySelectorAll('[data-session]').forEach(card=>{const visible=filter==='all'||card.dataset.type===filter||(filter==='joined'&&card.dataset.joined==='true');card.hidden=!visible;if(visible)count++});document.getElementById('noSessions').hidden=count>0}document.querySelectorAll('[data-filter]').forEach(button=>button.onclick=()=>{filter=button.dataset.filter;document.querySelectorAll('[data-filter]').forEach(item=>item.setAttribute('aria-pressed',String(item===button)));applyFilter()});
document.querySelectorAll('[data-join]').forEach(button=>button.onclick=()=>{const card=button.closest('[data-session]'),joined=card.dataset.joined!=='true';card.dataset.joined=String(joined);button.textContent=joined?'Leave preview':'Try joining';button.setAttribute('aria-pressed',String(joined));const count=Number(button.dataset.countBase)+(joined?1:0);card.querySelector('[data-count]').textContent=count;card.querySelector('.track .fill').style.width=(count/Number(button.dataset.limit)*100)+'%';applyFilter();const toast=document.getElementById('toast');toast.textContent=joined?'Added to My sessions in this preview only.':'Removed from your preview sessions.';toast.hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.hidden=true,3500)});
</script>
</div>
@endsection
