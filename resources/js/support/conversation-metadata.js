// Pure merge so the live group header stays in sync with conversation polling.
export function mergeConversationMetadata(current, latest) {
  if (!current || !latest || String(current.id) !== String(latest.id)) {
    return {value: current, headerChanged: false};
  }
  const headerChanged = current.name !== latest.name
    || current.group_photo_url !== latest.group_photo_url
    || current.profile_picture !== latest.profile_picture
    || current.blocked_by_me !== latest.blocked_by_me;
  return {value: {...current, ...latest}, headerChanged};
}
