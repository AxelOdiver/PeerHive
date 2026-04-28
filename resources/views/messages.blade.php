@extends('layouts.dashboard')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
<div class="card direct-chat direct-chat-primary mb-4" draggable="false" style="">
    <div class="card-header" style="cursor: move;">
      <h3 class="card-title">Direct Chat</h3>

      <div class="card-tools">
        <span title="3 New Messages" class="badge text-bg-primary"> 3 </span>
        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse">
          <i data-lte-icon="expand" class="bi bi-plus-lg"></i>
          <i data-lte-icon="collapse" class="bi bi-dash-lg"></i>
        </button>
        <button type="button" class="btn btn-tool" title="Contacts" data-lte-toggle="chat-pane">
          <i class="bi bi-chat-text-fill"></i>
        </button>
        <button type="button" class="btn btn-tool" data-lte-toggle="card-remove">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
    </div>
    <div class="card-body" style="box-sizing: border-box; display: block;">
      <div class="direct-chat-messages" style="height:400px">
        <div class="direct-chat-msg">
          <div class="direct-chat-infos clearfix">
            <span class="direct-chat-name float-start"> John Paul Castro </span>
            <span class="direct-chat-timestamp float-end"> 23 Jan 2:00 pm </span>
          </div>
          <img class="direct-chat-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="message user image" draggable="false">
          <div class="direct-chat-text">
            Is this template really for free? That's unbelievable!
          </div>
          </div>
        <div class="direct-chat-msg end">
          <div class="direct-chat-infos clearfix">
            <span class="direct-chat-name float-end"> Dominic Belen </span>
            <span class="direct-chat-timestamp float-start"> 23 Jan 2:05 pm </span>
          </div>
          <img class="direct-chat-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="message user image" draggable="false">
          <div class="direct-chat-text">You better believe it!</div>
          </div>
        <div class="direct-chat-msg">
          <div class="direct-chat-infos clearfix">
            <span class="direct-chat-name float-start"> John Paul Castro </span>
            <span class="direct-chat-timestamp float-end"> 23 Jan 5:37 pm </span>
          </div>
          <img class="direct-chat-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="message user image" draggable="false">
          <div class="direct-chat-text">
            Working with AdminLTE on a great new app! Wanna join?
          </div>
          </div>
        <div class="direct-chat-msg end">
          <div class="direct-chat-infos clearfix">
            <span class="direct-chat-name float-end"> Dominic Belen </span>
            <span class="direct-chat-timestamp float-start"> 23 Jan 6:10 pm </span>
          </div>
          <img class="direct-chat-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="message user image" draggable="false">
          <div class="direct-chat-text">I would love to.</div>
          </div>
        </div>
      <div class="direct-chat-contacts">
        <ul class="contacts-list">
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  Axel Odiver
                  <small class="contacts-list-date float-end"> 2/28/2023 </small>
                </span>
                <span class="contacts-list-msg"> How have you been? I was... </span>
              </div>
              </a>
          </li>
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  John Paul Castro
                  <small class="contacts-list-date float-end"> 2/23/2023 </small>
                </span>
                <span class="contacts-list-msg"> I will be waiting for... </span>
              </div>
              </a>
          </li>
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  Paul Briones
                  <small class="contacts-list-date float-end"> 2/20/2023 </small>
                </span>
                <span class="contacts-list-msg"> I'll call you back at... </span>
              </div>
              </a>
          </li>
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  Axel Odiver
                  <small class="contacts-list-date float-end"> 2/10/2023 </small>
                </span>
                <span class="contacts-list-msg"> Where is your new... </span>
              </div>
              </a>
          </li>
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  Dominic Belen
                  <small class="contacts-list-date float-end"> 1/27/2023 </small>
                </span>
                <span class="contacts-list-msg"> Can I take a look at... </span>
              </div>
              </a>
          </li>
          <li>
            <a href="#" draggable="false">
              <img class="contacts-list-img" src="https://www.gravatar.com/avatar/00000000000000000000000000000000?d=mp&f=y" alt="User Avatar" draggable="false">

              <div class="contacts-list-info">
                <span class="contacts-list-name">
                  Paul Briones
                  <small class="contacts-list-date float-end"> 1/4/2023 </small>
                </span>
                <span class="contacts-list-msg"> Never mind I found... </span>
              </div>
              </a>
          </li>
          </ul>
        </div>
      </div>
    <div class="card-footer border-0 bg-transparent">
  <div class="bg-body-secondary rounded-4 shadow-sm p-2 d-flex align-items-center">
    
    <input 
      type="text" 
      name="message" 
      placeholder="Type your message here..." 
      class="form-control border-0 bg-transparent shadow-none flex-grow-1"
    >

    <button type="button" class="btn btn-link text-secondary p-1 me-2">
      <i class="bi bi-emoji-smile-fill fs-5"></i>
    </button>
    
  </div>
</div>
    </div>
@endsection