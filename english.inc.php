<?php

//################################################################################################
//## PowerPHPBoard (C) Copyright 2001 by Stefan 'BFG' Kramer (bfg@ps-powerscripts.de).         ###
//## The PPB is free software and you can edit it under the terms of the PowerScript License.  ###
//################################################################################################

// English language file for the PowerPHPBoard.
// All texts are plain UTF-8 without HTML; the output escapes them.
// Placeholders such as %s and %1$s are replaced via sprintf().
// The three language files contain the same keys in the same order.

// General and navigation
$lang_htmllang = 'en';
$lang_home = 'Home';
$lang_mainnav = 'Main navigation';
$lang_togglenav = 'Toggle navigation';
$lang_breadcrumb = 'Breadcrumb';
$lang_actions = 'Actions';
$lang_admincenter = 'Administration';
$lang_statistics = 'Statistics';
$lang_profile = 'Profile';
$lang_login = 'Log in';
$lang_logout = 'Log out';
$lang_register = 'Register';
$lang_loggedinas = 'Logged in as';
$lang_statusmessage = 'Status';
$lang_errormessage = 'Error';
$lang_notice = 'Notice';
$lang_status = 'Status';
$lang_legend = 'Legend';
$lang_back = 'Back';
$lang_send = 'Send';
$lang_reset = 'Reset';
$lang_yes = 'yes';
$lang_no = 'no';
$lang_on = 'on';
$lang_off = 'off';
$lang_or = 'or';
$lang_by = 'by';
$lang_notspecified = 'Not specified';
$lang_description = 'Description';
$lang_csrfinvalid = 'The security token is invalid. Please reload the page and try again.';
$lang_toomanyattempts = 'Too many attempts. Please wait a while and try again.';
$lang_cannotbeundone = 'This action cannot be undone.';

// Board index
$lang_board = 'Board';
$lang_boardlist = 'Board list';
$lang_postings = 'Posts';
$lang_threads = 'Threads';
$lang_lastpost = 'Last post';
$lang_jumptolastpost = 'Jump to the last post';
$lang_nopostings = 'No posts';
$lang_noboardsincat = 'There are no boards in this category yet.';
$lang_nocatsindb = 'There are no board categories yet.';
$lang_moderatedby = 'Moderated by';
$lang_newpostings = 'New posts';
$lang_nonewpostings = 'No new posts';
$lang_closedboard = 'Closed board';
$lang_privateboard = 'Private board';
$lang_usersonline = 'Users online';
$lang_currentlyonline = 'Currently online';
$lang_noregisteredonline = 'No registered users online';

// Thread list
$lang_thread = 'Thread';
$lang_author = 'Author';
$lang_replys = 'Replies';
$lang_views = 'Views';
$lang_lastreply = 'Last reply';
$lang_noreplys = 'No replies';
$lang_nothreadsinboard = 'There are no threads in this board yet.';
$lang_newreplys = 'New replies';
$lang_nonewreplys = 'No new replies';
$lang_morethan15posts = 'More than 15 posts';
$lang_lockedthread = 'Locked thread';
$lang_jumptofirstunread = 'Jump to the first unread post';
$lang_newthread = 'New thread';
$lang_newpost = 'New post';
$lang_boardclosed = 'Board closed';
$lang_threadclosed = 'Thread closed';

// Private boards
$lang_thisboardrequirespwd = 'This board requires a password.';
$lang_threadrequirespwd = 'You need a password to read this thread.';
$lang_boardpassword = 'Board password';
$lang_bpwdnotcorrect = 'The board password is not correct.';
$lang_insertboardpwd = 'Please enter the board password.';
$lang_boardpasswordhelp = 'This area is private. Please enter the board password to access it.';
$lang_requestaccess = 'Unlock';

// Thread view
$lang_pages = 'Pages';
$lang_pagenavigation = 'Page navigation';
$lang_prevpage = 'Previous page';
$lang_nextpage = 'Next page';
$lang_nothreadwithid = 'There is no thread with this ID.';
$lang_nopostsinthread = 'There are no posts in this thread.';
$lang_anonymous = 'Unknown';
$lang_deactivated = 'Deactivated';
$lang_administrator = 'Administrator';
$lang_registeredsince = 'Registered since:';
$lang_postcount = 'Posts:';
$lang_postedon = 'Posted on';
$lang_postactions = 'Post actions';
$lang_writemail = 'Write an email to';
$lang_writequotedanswer = 'Reply with quote';
$lang_editpost = 'Edit post';
$lang_logged = 'show';
$lang_quote = 'Quote:';
$lang_image = 'Image';
$lang_backtoboard = 'Back to the board "%s"';
$lang_backtothread = 'Back to the thread "%s"';

// Ranks by number of posts
$lang_rank = 'Rank';
$lang_rank_newcomer = 'Newcomer';
$lang_rank_beginner = 'Beginner';
$lang_rank_member = 'Member';
$lang_rank_active = 'Active member';
$lang_rank_regular = 'Regular';
$lang_rank_experienced = 'Experienced member';
$lang_rank_seasoned = 'Seasoned member';
$lang_rank_expert = 'Expert';
$lang_rank_professional = 'Professional';
$lang_rank_veteran = 'Veteran';
$lang_rank_oldhand = 'Old hand';
$lang_rank_legend = 'Legend';

// New thread, new post, edit post
$lang_title = 'Title';
$lang_titlehelp = 'A meaningful title, up to 150 characters.';
$lang_icon = 'Icon';
$lang_noicon = 'No icon';
$lang_text = 'Text';
$lang_htmlcodeis = 'HTML is';
$lang_bbcodeis = 'BBCode is';
$lang_smiliesare = 'Smilies are';
$lang_inserttitle = 'Please enter a title.';
$lang_inserttext = 'Please enter a text.';
$lang_posttoolong = 'The post text is too long.';
$lang_insertvaluesforall = 'Please fill in all required fields.';
$lang_loginfirst = 'You have to log in first.';
$lang_chooseboard = 'Please choose a board.';
$lang_choosethread = 'Please choose a thread.';
$lang_choosepost = 'Please choose a post.';
$lang_nopostwithid = 'There is no post with this ID.';
$lang_boardclosedcannotopenthread = 'This board is closed. You cannot start new threads in a closed board.';
$lang_threadclosedcannotpost = 'This thread is closed. You cannot write new posts in a closed thread.';
$lang_openedthreadsuccessfull = 'The new thread has been created.';
$lang_newpostcreated = 'Your post has been created.';
$lang_notallowedtoeditpost = 'You are not allowed to edit this post.';
$lang_moderation = 'Moderation';
$lang_deletepost = 'Delete post';
$lang_deletethread = 'Delete thread';
$lang_closethread = 'Close thread';
$lang_closethreadhelp = 'Nobody can reply afterwards.';
$lang_openthread = 'Open thread';
$lang_threaddeleted = 'The thread has been deleted.';
$lang_threadclosedsuccess = 'The thread has been closed.';
$lang_threadopened = 'The thread has been opened.';
$lang_threadedited = 'The thread has been saved.';
$lang_postingdeleted = 'The post has been deleted.';
$lang_postingedited = 'The post has been saved.';
$lang_showboard = 'Show board';
$lang_showthread = 'Show thread';

// IP address
$lang_ipaddressforpost = 'IP address of post';
$lang_ipaddressis = 'The IP address is:';
$lang_postingdoesntbelongtothread = 'This post does not belong to this thread.';
$lang_onlyadminscanviewip = 'Only moderators and administrators can see IP addresses.';

// Log in and log out
$lang_email = 'Email address';
$lang_password = 'Password';
$lang_insertemail = 'Please enter your email address.';
$lang_insertvalidemail = 'Please enter a valid email address.';
$lang_insertpwd = 'Please enter your password.';
$lang_loginemailhelp = 'Please enter the email address you registered with.';
$lang_loginfailed = 'Login failed. The email address or password is incorrect, or the account has been deactivated.';
$lang_loginok = 'You have logged in successfully.';
$lang_pwdforgotten = 'Forgot your password?';
$lang_wanttoregister = 'Not registered yet?';
$lang_cookeisenabled = 'Cookies must be enabled.';
$lang_logoutok = 'You have logged out successfully.';
$lang_reallylogout = 'Do you really want to log out?';
$lang_yeslogout = 'Yes, log out';
$lang_nologout = 'No, stay logged in';

// Registration and profile
$lang_boardrules = 'Board rules';
$lang_boardrulescontent = 'This board works in real time, so the operator cannot review posts immediately. Please note that posts cannot be checked automatically. The author of a post alone is responsible for its content, accuracy and form. Racist, pornographic, degrading and immoral content is forbidden. Such posts will be deleted or edited by an administrator or moderator without notice. The operator of this board reserves the right to delete or edit users. Your data will not be passed on without your consent. We hope you enjoy the discussions with the other users.';
$lang_agree = 'I agree';
$lang_disagree = 'I disagree';
$lang_requiredinfo = 'Required information';
$lang_optionalinfo = 'Optional information';
$lang_othersettings = 'Other settings';
$lang_username = 'Username';
$lang_usernamehelp = '2 to 50 characters: letters (no umlauts), digits and . _ -';
$lang_usernameinvalid = 'The username must be 2 to 50 characters long and may only contain letters (no umlauts), digits and . _ -';
$lang_usernametaken = 'This username is already taken.';
$lang_confirmation = 'Confirmation';
$lang_repeatemailhelp = 'Please enter the email address again to confirm it.';
$lang_emailsdifferent = 'The two email addresses do not match.';
$lang_emailnotcorrect = 'The email address is not valid.';
$lang_emailalreadyexists = 'This email address is already registered.';
$lang_pwdminlength = 'At least 8 characters.';
$lang_pwdtooshort = 'The password must be at least 8 characters long.';
$lang_repeatpwd = 'Please enter the password again.';
$lang_pwdsdifferent = 'The two passwords do not match.';
$lang_homepage = 'Homepage';
$lang_homepagehelp = 'Optional. https:// is added automatically if missing.';
$lang_homepageinvalid = 'Please enter a valid homepage address starting with http:// or https://.';
$lang_icq = 'ICQ number';
$lang_icqnotcorrect = 'The ICQ number may only contain digits.';
$lang_biography = 'About me';
$lang_writesomethingaboutyou = 'Tell us something about yourself';
$lang_signature = 'Signature';
$lang_hideemail = 'Hide email address?';
$lang_hideemailhelp = 'If enabled, other users cannot see your email address.';
$lang_saveloginincookie = 'Remember login?';
$lang_inputstoolong = 'At least one field is too long.';
$lang_errorwhilereg = 'An error occurred during registration. Please try again.';
$lang_registrationsuccessfull = 'Your registration was successful. You can log in now.';
$lang_confirmationmailfailed = 'The confirmation email could not be sent. You can still log in.';
$lang_security = 'Security:';
$lang_currentpassword = 'Current password';
$lang_currentpwdnote = 'Only required if you change your email address or password.';
$lang_currentpasswordwrong = 'The current password is not correct.';
$lang_newpassword = 'New password';
$lang_leaveemptynochange = 'Leave empty to keep the current password.';
$lang_changedprofilesuccessfull = 'Your profile has been saved.';
$lang_errorwhileupdprofile = 'An error occurred while saving your profile. Please try again.';
$lang_showuserprof = 'User profile';
$lang_chooseuser = 'Please choose a user.';
$lang_nouserwithid = 'There is no user with this ID.';
$lang_profileof = 'Profile of %s';

// Email to users
$lang_sendmail = 'Send email';
$lang_from = 'From';
$lang_to = 'To';
$lang_subject = 'Subject';
$lang_insertsubject = 'Please enter a subject.';
$lang_mailsubjectdefault = 'Message from the forum';
$lang_chooseexistinguser = 'Please choose an existing user.';
$lang_emailsentsuccessfull = 'The email has been sent.';
$lang_emailsendfailed = 'The email could not be sent. Please try again later.';
$lang_mailsentvia = '%1$s sent you this email via the forum %2$s. Replies go directly to %1$s.';

// Forgotten password
$lang_sendpwd = 'Forgot your password';
$lang_backtologin = 'Back to login';
$lang_pwdresethelp = 'If this email address is registered, we will send you a one-time link to reset your password.';
$lang_pwdresetlinksent = 'If the email address is registered, we have sent you a link to reset your password.';
$lang_pwdresetunavailable = 'Password reset is currently unavailable. Please contact the board administrator.';
$lang_passwordreminder = 'Reset password';
$lang_pwdresetclicklink = 'Click this link within one hour to reset your password:';
$lang_ifyoudidntrequestmail = 'If you did not request this, you can ignore this email.';
$lang_pwdresettokeninvalid = 'The reset link is invalid or has expired.';
$lang_pwdresetsuccess = 'Your password has been changed. You can log in now.';

// Email texts
$lang_registration = 'Registration';
$lang_hello = 'Hello';
$lang_youregisteredsuccessfull = 'you have successfully registered at';
$lang_accountcreatedbyadmin = 'an account has been created for you at';
$lang_hereisyourlogininformation = 'Your login details:';
$lang_passwordfromadmin = 'You will receive your password from the board administrator. You can set your own password at any time via "Forgot your password?".';
$lang_youcanloginhere = 'You can log in here:';
$lang_donotanswertoautomail = 'This email was generated automatically. Please do not reply to it.';

// Statistics
$lang_numregistered = 'Registered users';
$lang_numregistereddesc = 'Number of all registered users';
$lang_numthreads = 'Threads';
$lang_numthreadsdesc = 'Number of all threads';
$lang_numposts = 'Posts';
$lang_numpostsdesc = 'Number of all posts including thread starts';

// Smilies and BBCode
$lang_smilielist = 'Smilies';
$lang_command = 'Command';
$lang_action = 'Result';
$lang_bbcommans = 'BBCode commands';
$lang_bbintro = 'Addresses in [url] and [img] must start with http:// or https://; addresses starting with www. are completed automatically.';
$lang_bbexampletext = 'Text';
$lang_bbbold = 'Bold text';
$lang_bbitalic = 'Italic text';
$lang_bbunderlined = 'Underlined text';
$lang_bbstrike = 'Strikethrough text';
$lang_bbquote = 'Quote';
$lang_bbcodeblock = 'Code block, shown exactly as written';
$lang_bburl = 'Link to an address';
$lang_bburlis = 'Link with its own text';
$lang_bbimg = 'Image from an address';
$lang_bbimgresult = 'The image is displayed.';
$lang_bbautolink = 'Web and email addresses are linked automatically';
$lang_htmlallowedtags = 'HTML is enabled. These tags are allowed, without attributes:';

// Administration
$lang_adm_title = 'Administration';
$lang_adm_nav = 'Administration navigation';
$lang_adm_overview = 'Overview';
$lang_adm_general = 'General';
$lang_adm_boards = 'Boards';
$lang_adm_users = 'Users';
$lang_adm_forum = 'To the forum';
$lang_adm_noaccess = 'No access.';
$lang_adm_noaccesstext = 'This area is only available to administrators.';
$lang_adm_welcome = 'Welcome, %s. Please choose an area.';
$lang_adm_generalsettings = 'General settings';
$lang_adm_generaldesc = 'Board name and address, administrator email, language, default design and features.';
$lang_adm_configure = 'Configure';
$lang_adm_boardsdesc = 'Create, edit, close or delete categories and boards.';
$lang_adm_manage = 'Manage';
$lang_adm_usermanagement = 'User management';
$lang_adm_usersdesc = 'Create and edit users, grant administrator rights or deactivate accounts.';
$lang_adm_backtooverview = 'Back to the overview';
$lang_adm_save = 'Save';
$lang_adm_cancel = 'Cancel';
$lang_adm_edit = 'Edit';
$lang_adm_action = 'Action';
$lang_adm_fillrequired = 'Please fill in all required fields.';
$lang_adm_templateinvalid = 'Header and footer templates must be file names from the inc/ folder or stay empty.';

// Administration: general settings
$lang_adm_generalintro = 'Name, address and language of the board, default design and features.';
$lang_adm_settingssaved = 'The settings have been saved.';
$lang_adm_generalinfo = 'General information';
$lang_adm_boardtitle = 'Board title';
$lang_adm_boardtitlehelp = 'Shown at the top left of the navigation bar and in the browser tab.';
$lang_adm_insertboardtitle = 'Please enter a board title.';
$lang_adm_boardurl = 'Board URL';
$lang_adm_boardurlhelp = 'Address of the board, e.g. https://forum.example.org. It is used for links in emails (registration, forgotten password).';
$lang_adm_boardurlfeedback = 'Please enter the full address starting with https:// or http://.';
$lang_adm_boardurlinvalid = 'Please enter a valid board URL starting with http:// or https://, e.g. https://forum.example.org.';
$lang_adm_adminemail = 'Administrator email';
$lang_adm_adminemailhelp = 'Sender address of the emails sent by the board.';
$lang_adm_adminemailinvalid = 'Please enter a valid administrator email address.';
$lang_adm_language = 'Language';
$lang_adm_defaultdesign = 'Default design';
$lang_adm_designnote_general = 'The Bootstrap 5 layout no longer uses the colour and button image fields. They are optional, kept for compatibility and passed on as defaults to new boards and categories.';
$lang_adm_designnote_new = 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; the defaults come from the general settings.';
$lang_adm_designnote_edit = 'The Bootstrap 5 layout no longer uses these fields. They are optional and kept for compatibility; changes here have no visible effect in the board.';
$lang_adm_headertemplate = 'Custom header template';
$lang_adm_footertemplate = 'Custom footer template';
$lang_adm_templatehelp = 'File name from the inc/ folder; empty = default.';
$lang_adm_bordercolor = 'Border colour';
$lang_adm_colorhelp = 'Hex colour code, e.g. #000000';
$lang_adm_tablebg1 = 'Table background 1';
$lang_adm_tablebg1help = 'Light row';
$lang_adm_tablebg2 = 'Table background 2';
$lang_adm_tablebg2help = 'Alternating row';
$lang_adm_tablebg3 = 'Table background 3';
$lang_adm_tablebg3help = 'Table header';
$lang_adm_newthreadimage = 'Image for the "New thread" button';
$lang_adm_newpostimage = 'Image for the "New post" button';
$lang_adm_buttonimagehelp = 'Path to a 120 × 20 pixel image, e.g. images/newthread.gif';
$lang_adm_features = 'Features';
$lang_adm_htmlinposts = 'HTML in posts';
$lang_adm_htmlhelp = 'Only simple formatting tags without attributes (b, i, u, p, ul, li …); links and images via BBCode.';
$lang_adm_bbcodeinposts = 'BBCode in posts';
$lang_adm_smiliesinposts = 'Smilies in posts';
$lang_adm_savesettings = 'Save settings';

// Administration: boards and categories
$lang_adm_boardmanagement = 'Board management';
$lang_adm_boardactions = 'Board actions';
$lang_adm_categoryactions = 'Category actions';
$lang_adm_addboard = 'Add board';
$lang_adm_addcategory = 'Add category';
$lang_adm_editboard = 'Edit board';
$lang_adm_editcategory = 'Edit category';
$lang_adm_applydesign = 'Apply design';
$lang_adm_nocategories = 'There are no board categories yet. Please create at least one category.';
$lang_adm_nocategoriesyet = 'There are no categories yet.';
$lang_adm_status_open = 'Open';
$lang_adm_status_closed = 'Closed';
$lang_adm_status_private = 'Private';
$lang_adm_statushelp = '"Closed" blocks new threads and replies, "Private" requires a password.';
$lang_adm_boardinfo = 'Board information';
$lang_adm_moderators = 'Moderators';
$lang_adm_modshelp = 'Comma-separated list of email addresses, e.g. anna@example.org, moritz@example.org';
$lang_adm_category = 'Category';
$lang_adm_boardpassword = 'Board password (only for "Private")';
$lang_adm_boardpasswordhashed = 'Only stored as a hash.';
$lang_adm_boardpasswordset = 'A password is set and only stored as a hash. Leave empty to keep it.';
$lang_adm_boardpasswordrequired = 'Required if the status is "Private".';
$lang_adm_privateneedspassword = 'A password is required for the status "Private".';
$lang_adm_titleandcategory = 'Please enter a title and choose a category.';
$lang_adm_legacydesign = 'Design (legacy fields)';
$lang_adm_boardcreated = 'The board has been created.';
$lang_adm_boardsaved = 'The board has been saved.';
$lang_adm_boarddeleted = 'The board has been deleted.';
$lang_adm_boardnotfound = 'There is no board with this ID.';
$lang_adm_backtoboards = 'Back to board management';
$lang_adm_dangerzone = 'Danger zone';
$lang_adm_boardhasthreads = 'Threads in this board: %d. Deleting the board removes all threads and posts permanently.';
$lang_adm_deleteboard = 'Delete this board';
$lang_adm_deleteconfirm = 'Deletion confirmation: I understand that all threads of this board (%d) including all posts will be removed permanently.';
$lang_adm_deleteneedsconfirm = 'This board contains threads (%d). Tick "Deletion confirmation" as well if all threads and posts should be deleted.';
$lang_adm_saveoraction = 'Save / perform action';
$lang_adm_insertcategorytitle = 'Please enter a category title.';
$lang_adm_categorycreated = 'The category has been created.';
$lang_adm_categorysaved = 'The category has been saved.';
$lang_adm_categorydeleted = 'The category has been deleted.';
$lang_adm_categorynotfound = 'There is no category with this ID.';
$lang_adm_categoryhasboards = 'Boards in this category: %d. The category can only be deleted once all boards have been moved or deleted.';
$lang_adm_categorynotempty = 'The category cannot be deleted because it still contains boards (%d). Please move or delete them first.';
$lang_adm_showboardsincategory = 'Show the boards of this category';
$lang_adm_deletecategory = 'Delete this category';
$lang_adm_boarddesign = 'Board design';
$lang_adm_designapplied = 'The design has been applied.';
$lang_adm_applycategorydesign_title = 'Apply the category design to all boards of this category';
$lang_adm_applycategorydesign_text = 'Do you really want all boards of this category to take over the design of the category?';
$lang_adm_applydefaultdesign_title = 'Apply the default design to all boards';
$lang_adm_applydefaultdesign_text = 'Do you really want all boards and categories to take over the default design?';
$lang_adm_yesapply = 'Yes, apply';

// Administration: users
$lang_adm_adduser = 'Add user';
$lang_adm_edituser = 'Edit user';
$lang_adm_searchuser = 'Search users';
$lang_adm_search = 'Search';
$lang_adm_searchhelp = 'Part of the name is enough. Leave empty to see all users in the list below.';
$lang_adm_all = 'All';
$lang_adm_administrators = 'Administrators';
$lang_adm_normalusers = 'Normal users';
$lang_adm_status_normal = 'Normal user';
$lang_adm_searchresults = 'Search results for "%s"';
$lang_adm_userlist = 'User list';
$lang_adm_nousersfound = 'No users found.';
$lang_adm_nousersinlist = 'There are no users in this list.';
$lang_adm_registered = 'Registered';
$lang_adm_pageof = 'Page %1$d of %2$d (%3$d users)';
$lang_adm_usercreated = 'The user %s has been created.';
$lang_adm_mailsent = 'A notification has been sent by email.';
$lang_adm_mailfailed = 'The email notification could not be sent (see the error log for details).';
$lang_adm_tousermanagement = 'To user management';
$lang_adm_backtousermanagement = 'Back to user management';
$lang_adm_insertusername = 'Please enter a username.';
$lang_adm_emailsdifferent = 'The email addresses do not match.';
$lang_adm_pwdsdifferent = 'The passwords do not match.';
$lang_adm_emailtaken = 'This email address already belongs to another user.';
$lang_adm_usercreatefailed = 'The user could not be created.';
$lang_adm_userupdatefailed = 'The changes could not be saved.';
$lang_adm_usernotfound = 'There is no user with this ID.';
$lang_adm_changessaved = 'The changes have been saved.';
$lang_adm_basicdata = 'Basic data';
$lang_adm_statusadminwarning = 'Note: the status "Administrator" grants full access to the administration.';
$lang_adm_ownstatus = 'You cannot change your own status, otherwise the administration would no longer be accessible to you. Only another administrator can do this.';
$lang_adm_lastadmin = 'The last administrator can neither be downgraded nor deactivated.';
