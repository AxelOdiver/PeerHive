# Community previews and join approvals

Both public and private community cards are clickable to open their details. Non-members keep the Join Community button; members and admins keep View Community. Join and delete controls operate separately from the card link. Public posts and comments are readable by signed-in users; posting and commenting still require membership. Private previews show basic community information and the member count, but never load posts, comments, member lists, or session details for non-members. Existing administrator access is preserved.

Private membership supports owner/admin approval and existing invitations. Requests can be cancelled or declined. Approval and invitation acceptance lock the community row and check capacity, counting the owner even for older communities without an owner pivot row. Accepting an invitation resolves a pending join request; approval resolves a pending invitation. Owners receive request notifications and applicants receive decision notifications.

Apply the additive migration with `php artisan migrate`. It creates only `community_join_requests`; existing memberships, invitations, and content are retained.

Pre-change source copies are in the local ignored `storage/community-before-20261103.zip`. Restore the six files from that archive to their original paths and remove the newly added request controller/model/views/routes if reverting this feature. Keep the new table when reverting code to retain request history, or roll back this migration specifically only if deleting join-request history is acceptable. Do not reset or refresh the database.
