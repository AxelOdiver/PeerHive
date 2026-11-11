@extends('layouts.dashboard')

@section('title', 'Users')

@section('content')
<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <x-page-header title="Users" subtitle="Browse the user directory and export the table when needed.">
            <button type="button" class="btn btn-primary create-user">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Create user
            </button>
        </x-page-header>
        
        <div id="usersTableShell" class="table-responsive users-table-shell is-loading">
            <div class="users-table-loading text-muted small py-4">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span class="ms-2">Loading users...</span>
            </div>

            <table id="usersTable" class="table table-striped table-hover align-middle w-100 users-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        </div>
    </div>
    
    <x-modal id="userModal" title="">
        <form id="userForm">
            @csrf
            <input type="hidden" id="editUserId" name="user_id" value="">
            <div class="form-floating">
                <input type="text" class="form-control mt-3" id="editUserFirstName" name="first_name" placeholder="First Name">
                <label for="editUserFirstName">First Name</label>
            </div>
            <div class="form-floating">
                <input type="text" class="form-control mt-3" id="editUserMiddleName" name="middle_name" placeholder="Middle Name">
                <label for="editUserMiddleName">Middle Name</label>
            </div>
            <div class="form-floating">
                <input type="text" class="form-control mt-3" id="editUserLastName" name="last_name" placeholder="Last Name">
                <label for="editUserLastName">Last Name</label>
            </div>
            <div class="form-floating">
                <input type="email" class="form-control mt-3" id="editUserEmail" name="email" placeholder="Email">
                <label for="editUserEmail">Email</label>
            </div>
            <div id="passwordFields">
                <div class="form-floating">
                    <input type="password" class="form-control mt-3" id="editUserPassword" name="password" placeholder="Password" autocomplete="new-password">
                    <label for="editUserPassword">Password</label>
                </div>
                <div class="form-text password-hint">
                    Leave this blank to keep the current password.
                </div>

                <div id="confirmPasswordWrap" class="fade-field">
                    <div class="form-floating">
                        <input type="password" class="form-control mt-3" id="editUserPasswordConfirm" name="password_confirmation" placeholder="Confirm Password" autocomplete="new-password">
                        <label for="editUserPasswordConfirm">Confirm Password</label>
                    </div>
                </div>
            </div>
        </form>
        
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary" id="sendEditUserBtn" form="userForm">Save changes</button>
        </x-slot:footer>
    </x-modal>
@endsection
