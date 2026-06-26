const MyRsuIcons = (() => {
  const icons = {
    active: '<svg viewBox="0 0 24 24"><path d="M9 12l2 2 4-5"/><circle cx="12" cy="12" r="9"/></svg>',
    document: '<svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/></svg>',
    download: '<svg viewBox="0 0 24 24"><path d="M12 4v10"/><path d="M8 10l4 4 4-4"/><path d="M5 19h14"/></svg>',
    edit: '<svg viewBox="0 0 24 24"><path d="M4 20h4l11-11-4-4L4 16v4z"/><path d="M13 7l4 4"/></svg>',
    eye: '<svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/></svg>',
    logs: '<svg viewBox="0 0 24 24"><path d="M5 5h14M5 12h14M5 19h10"/></svg>',
    link: '<svg viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/></svg>',
    protocolDelete: '<svg viewBox="0 0 24 24"><path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M7 7l1 13h8l1-13"/><path d="M10 11v6M14 11v6"/></svg>',
    protocolIn: '<svg viewBox="0 0 24 24"><path d="M12 20V8"/><path d="M8 12l4-4 4 4"/><path d="M5 4h14"/></svg>',
    save: '<svg viewBox="0 0 24 24"><path d="M5 4h12l2 2v14H5z"/><path d="M8 4v6h8M8 20v-6h8"/></svg>',
    shield: '<svg viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-3 8-8 9-5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-5"/></svg>',
    suspended: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M8 8l8 8"/></svg>',
    trash: '<svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13"/></svg>',
  };

  function get(name) {
    return icons[name] || '';
  }

  function status(status) {
    return status === 'active' ? get('active') : get('suspended');
  }

  return { get, status };
})();
