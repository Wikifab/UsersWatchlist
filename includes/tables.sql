
CREATE TABLE /*_*/userswatchlist (
  -- Key to user.user_id
  fl_user int unsigned NOT NULL,

  -- Key to followed user
  fl_user_followed int unsigned NOT NULL,

  -- Timestamp used to send notification e-mails and show "updated since last visit" markers on
  -- history and recent changes / watchlist. Set to NULL when the user visits the latest revision
  -- of the page, which means that they should be sent an e-mail on the next change.
  fl_notificationtimestamp varbinary(14)

) /*$wgDBTableOptions*/;

CREATE UNIQUE INDEX /*i*/fl_user ON /*_*/userswatchlist (fl_user, fl_user_followed);
CREATE INDEX /*i*/fl_notificationtimestamp ON /*_*/userswatchlist (fl_user, fl_notificationtimestamp);


