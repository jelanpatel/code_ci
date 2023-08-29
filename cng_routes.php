<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'home';
$route['404_override'] = '';
$route['worker'] = 'background/worker';
$route['translate_uri_dashes'] = FALSE;
$route['image/(:any)/(:any)/(:any)'] = 'home/image/$3/$1/$2';
$route['image/(:any)'] = 'home/image/$1';
$route['contact-us'] = 'home/contactUs';
$route['blog'] = 'home/blog';
$route['app-link/(:any)/(:any)'] = 'AppLink/index/$1/$2';
$route['app-link/(:any)'] = 'AppLink/index/$1';
$route['apple-app-site-association'] = 'AppLink/appleAppSiteAssociation';

$route['social/(:any)'] = 'auth/socialcallback/$1';
$route['socialemail'] = 'auth/socialEmail';

$route['google/calendar'] = 'auth/google_calendar';
$route['google/discalendar'] = 'auth/google_discalendar';
$route['google/calendarsync'] = 'auth/google_calendarsync';
$route['google/calendarconnect/(:any)'] = 'auth/google_calendarconnect/$1';

$route['login'] = 'auth/login';
$route['signup'] = 'auth/signup';
$route['signup-user'] = 'auth/signup_user';
$route['signup-completed'] = 'auth/signup_completed';
$route['sign-in'] = 'auth/signIn';
$route['verify/(:any)'] = 'auth/verifyAccount/$1';
$route['addlocation/(:any)'] = 'auth/adduserlocation/$1';
$route['cardList'] = 'auth/cardList';
$route['logout'] = 'auth/logout';
$route['paymentSuceess'] = 'auth/paymentSuceess';
$route['forgotpassword'] = 'auth/forgotpassword';
$route['verifyAccount/(:any)'] = 'auth/verify/$1';
$route['changePassword/(:any)'] = 'auth/changePassword/$1';

$route['terms-of-service'] = 'front/cms/terms';
$route['privacy-policy'] = 'front/cms/privacy';
$route['provider-agreement'] = 'front/cms/provider_agreement';
$route['app-eula'] = 'front/cms/app_eula';
$route['stripe-onboard-return'] = 'home/stripeOnboardReturn';
$route['stripe-onboard-refresh'] = 'home/stripeOnboardRefresh';

/* patient */
defined('ABOUT_US') OR define('ABOUT_US', 'patients/about-us');
defined('TERMS') OR define('TERMS', 'patients/term');
defined('PRIVACY') OR define('PRIVACY', 'patients/privacy-policy');
defined('APPLICENSEAGREE') OR define('APPLICENSEAGREE', 'patients/app-license-agreement');
defined('FAQ')  OR define('FAQ', 'patients/faq');
defined('PROFILE') OR define('PROFILE', 'patients/profile');
defined('CHANGEPASSWORD') OR define('CHANGEPASSWORD', 'patients/changePassword');
defined('SUPPORT') OR define('SUPPORT', 'patients/support');
defined('DASHBOARD') OR define('DASHBOARD', 'patients/dashboard');
defined('DASHBOARDSERVICE') OR define('DASHBOARDSERVICE', 'patients/dashboard');
defined('MEDICAL') OR define('MEDICAL', 'patients/medical-history');
defined('BLOG') OR define('BLOG', 'patients/blog');
defined('BLOG_DETAILS') OR define('BLOG_DETAILS', 'patients/blog-details');
defined('PAYMENT_BILLING') OR define('PAYMENT_BILLING', 'patients/payment-billing');
defined('REMOVE_CARD') OR define('REMOVE_CARD', 'patients/remove-card');
defined('NOTIFY') OR define('NOTIFY', 'patients/notification');
defined('MESSAGE') OR define('MESSAGE', 'patients/message');
defined('MESSAGEID') OR define('MESSAGEID', 'patients/message');
defined('CARD_DEF') OR define('CARD_DEF', 'patients/card-default');
defined('DOCTOR_PATIENT_LIST') OR define('DOCTOR_PATIENT_LIST', 'patients/doctor-list');
defined('DOCTOR_PROFILE_SHARE') OR define('DOCTOR_PROFILE_SHARE', 'patients/provider-profile');
defined('DOCTOR_SHARE_LIST') OR define('DOCTOR_SHARE_LIST', 'patients/doctor-share-list');
defined('SCHEDULEAPPOI') OR define('SCHEDULEAPPOI', 'patients/schedule-appointment');
defined('APPOINTMENT') OR define('APPOINTMENT', 'patients/appointment');
defined('DOCUMENTREPORT') OR define('DOCUMENTREPORT', 'patients/document-report');
defined('RESCHEDULEAPPOINTMENT') OR define('RESCHEDULEAPPOINTMENT', 'patients/reschdule');
defined('FAVORITE') OR define('FAVORITE', 'patients/favorite');
defined('QUICKAPPOINTMENT') OR define('QUICKAPPOINTMENT', 'patients/quick-appointment');
defined('VIDEOCALL') OR define('VIDEOCALL', 'patients/videocall');
defined('PLANS') OR define('PLANS', 'patients/plans');
defined('USERDOCUMENTREPORTVIEW') OR define('USERDOCUMENTREPORTVIEW', 'patients/document-view');


/*  doctor  */ 
defined('DASHBOARD_DOCTOR') OR define('DASHBOARD_DOCTOR', 'doctor/dashboard');
defined('ABOUT_US_DOCTOR') OR define('ABOUT_US_DOCTOR', 'doctor/about-us');
defined('TERMS_DOCTOR') OR define('TERMS_DOCTOR', 'doctor/term');
defined('PRIVACY_DOCTOR') OR define('PRIVACY_DOCTOR', 'doctor/privacy_policy');
defined('APPLICENSEAGREE_DOCTOR') OR define('APPLICENSEAGREE_DOCTOR', 'doctor/app-license-agreement');
defined('FAQ_DOCTOR') OR define('FAQ_DOCTOR', 'doctor/faq');
defined('PROFILE_DOCTOR') OR define('PROFILE_DOCTOR', 'doctor/profile');
defined('CHANGE_PASSWORD') OR define('CHANGE_PASSWORD', 'doctor/changePassword');
defined('SUBSCRIPTION') OR define('SUBSCRIPTION', 'doctor/subscription');
defined('SUPPORT_DASHBOARD') OR define('SUPPORT_DASHBOARD', 'doctor/support');
defined('BLOG_DASHBOARD') OR define('BLOG_DASHBOARD', 'doctor/blog');
defined('BLOG_DETAILS_DASHBOARD') OR define('BLOG_DETAILS_DASHBOARD', 'doctor/blog-details');
defined('CANCEL_SUB_DASHBOARD') OR define('CANCEL_SUB_DASHBOARD', 'doctor/cancel-subscription');
defined('PAYMENT_DASHBOARD') OR define('PAYMENT_DASHBOARD', 'doctor/payment');
defined('CARD_DEF_DASHBOARD') OR define('CARD_DEF_DASHBOARD', 'doctor/card-default');
defined('REMOVE_CARD_DASHBOARD') OR define('REMOVE_CARD_DASHBOARD', 'doctor/remove-card');
defined('NOTIFY_DOCTOR') OR define('NOTIFY_DOCTOR', 'doctor/notification');
defined('AVAIL_DOCTOR') OR define('AVAIL_DOCTOR', 'doctor/availability');
defined('APPOINTMENT_DOCTOR') OR define('APPOINTMENT_DOCTOR', 'doctor/appointment');
defined('MESSAGE_DOCTOR') OR define('MESSAGE_DOCTOR', 'doctor/message');
defined('MESSAGEID_DOCTOR') OR define('MESSAGEID_DOCTOR', 'doctor/message');
defined('RESCHEDULEAPPOINTMENT_DOCTOR') OR define('RESCHEDULEAPPOINTMENT_DOCTOR', 'doctor/reschdule');
defined('WALLET_DOCTOR') OR define('WALLET_DOCTOR', 'doctor/wallet');
defined('INVITE_CODE_DOCTOR') OR define('INVITE_CODE_DOCTOR', 'doctor/invite-client');
defined('REVIEW_DOCTOR') OR define('REVIEW_DOCTOR', 'doctor/my-review');
defined('PLANS_DOCTOR') OR define('PLANS_DOCTOR', 'doctor/plans');
defined('VIDEOCALL_DOCTOR') OR define('VIDEOCALL_DOCTOR', 'doctor/videocall');
defined('SERVICES_DOCTOR') OR define('SERVICES_DOCTOR', 'doctor/services');
defined('SERVICES_SHARE_DOCTOR') OR define('SERVICES_SHARE_DOCTOR', 'doctor/share-services');
defined('DOCTORDOCUMENTREPORT') OR define('DOCTORDOCUMENTREPORT', 'doctor/document-report');
defined('DOCTORDOCUMENTREPORTVIEW') OR define('DOCTORDOCUMENTREPORTVIEW', 'doctor/document-view');

defined('DOCTOR_STRIPE') OR define('DOCTOR_STRIPE', 'doctor/stripe');

defined('BANK_DOCTOR') OR define('BANK_DOCTOR', 'doctor/bank-detail');

defined('CANCELPOLICY') OR define('CANCELPOLICY', 'patients/cancelation-policy');

/* share links */
defined('SERVICEBOOKING') OR define('SERVICEBOOKING', 'service_booking');
defined('FREESERVICEBOOKING') OR define('FREESERVICEBOOKING', 'service-booking');
defined('SERVICEBOOKINGSUCESS') OR define('SERVICEBOOKINGSUCESS', 'service_sucess');
defined('PROVIDEPROFILE') OR define('PROVIDEPROFILE', 'provider-profile');
$route[SERVICEBOOKING.'/(:any)'] = 'home/service_book/$1';
$route[FREESERVICEBOOKING.'/(:any)/(:any)'] = 'home/service_book/$1/$2';
$route[SERVICEBOOKINGSUCESS.'/(:any)'] = 'home/service_sucess/$1';
$route[PROVIDEPROFILE.'/(:any)'] = 'home/provider_profile/$1';

$patient = 'front/patients/';
$route[DASHBOARD] = $patient.'dashboard/index';
$route[DASHBOARDSERVICE.'/(:any)'] = $patient.'dashboard/index/$1';
$route[ABOUT_US] = $patient.'dashboard/about_us';
$route[TERMS] = $patient.'dashboard/term';
$route[PRIVACY] = $patient.'dashboard/privacy_policy';
$route[CANCELPOLICY] = $patient.'dashboard/cancelationpolicy';
$route[APPLICENSEAGREE] = $patient.'dashboard/appLicensAgree';
$route[FAQ] = $patient.'dashboard/faq';
$route[NOTIFY] = $patient.'dashboard/notification';

$route[PROFILE] = $patient.'profile/index';
$route[CHANGEPASSWORD] = $patient.'profile/changePassword';

$route[SUPPORT] = $patient.'support/index';
$route[MEDICAL] = $patient.'medicalHistory/index';

$route[MESSAGEID."/(:any)"] = $patient.'message/index/$1';
$route[MESSAGE] = $patient.'message/index';

$route[PAYMENT_BILLING] = $patient.'paymentBilling/index';
$route[REMOVE_CARD."/(:any)"] = $patient.'paymentBilling/remove_card/$1';
$route[CARD_DEF."/(:any)"] = $patient.'paymentBilling/card_default/$1';

$route[DOCTOR_PATIENT_LIST."/(:any)"] = $patient.'doctorList/index/$1';
$route[SCHEDULEAPPOI."/(:any)"] = $patient.'doctorList/scheduleAppointment/$1';

$route[BLOG] = $patient.'blog/index';
$route[BLOG_DETAILS."/(:any)"] = $patient.'blog/blog_details/$1';

$route[APPOINTMENT] = $patient.'appointment/index';
$route[RESCHEDULEAPPOINTMENT."/(:any)/(:any)/(:any)/(:any)"] = $patient.'appointment/rescheduleAppointment/$1/$2/$3/$4';

$route[DOCUMENTREPORT] = $patient.'documentReport/index';
$route[USERDOCUMENTREPORTVIEW."/(:any)"] = $patient.'documentReport/documentView/$1';

$route[FAVORITE] = $patient.'favorite/index';
$route[DOCTOR_PROFILE_SHARE."/(:any)"] = $patient.'favorite/index/$1';
$route[DOCTOR_SHARE_LIST."/(:any)/(:any)"] = $patient.'favorite/shareDoctorProfile/$1/$2';

$route[QUICKAPPOINTMENT."/(:any)/(:any)"] = $patient.'QuickAppointment/quick_appointment/$1/$2';

$route[VIDEOCALL."/(:any)/(:any)"] = $patient.'videocall/index/$1/$2';

$route[PLANS] = $patient.'plans/index';


$doctor_path = 'front/doctor/';
$route[DASHBOARD_DOCTOR] = $doctor_path.'dashboard/index';
$route[ABOUT_US_DOCTOR] = $doctor_path.'dashboard/about_us';
$route[TERMS_DOCTOR] = $doctor_path.'dashboard/term';
$route[PRIVACY_DOCTOR] = $doctor_path.'dashboard/privacy_policy';
$route[APPLICENSEAGREE_DOCTOR] = $doctor_path.'dashboard/appLicenceAgree';
$route[FAQ_DOCTOR] = $doctor_path.'dashboard/faq';
$route[NOTIFY_DOCTOR] = $doctor_path.'dashboard/notification';
$route[INVITE_CODE_DOCTOR."/(:any)"] = $doctor_path.'dashboard/shareInviteCode/$1';
$route[SUPPORT_DASHBOARD] = $doctor_path.'support/index';

$route[PROFILE_DOCTOR] = $doctor_path.'profile/index';
$route[CHANGE_PASSWORD] = $doctor_path.'profile/changePassword';

$route[BLOG_DASHBOARD] = $doctor_path.'blog/index';
$route[BLOG_DETAILS_DASHBOARD."/(:any)"] = $doctor_path.'blog/blog_details/$1';

$route[SUBSCRIPTION] = $doctor_path.'subscription/index';
$route[CANCEL_SUB_DASHBOARD] = $doctor_path.'subscription/cancelSubscription';

$route[PAYMENT_DASHBOARD.'/(:any)'] = $doctor_path.'payment/index/$1';
$route[REMOVE_CARD_DASHBOARD."/(:any)/(:any)"] = $doctor_path.'payment/remove_card/$1/$2';
$route[CARD_DEF_DASHBOARD."/(:any)/(:any)"] = $doctor_path.'payment/card_default/$1/$2';

$route[AVAIL_DOCTOR] = $doctor_path.'availability/index';

$route[APPOINTMENT_DOCTOR] = $doctor_path.'appointment/index';

$route[MESSAGEID_DOCTOR."/(:any)"] = $doctor_path.'message/index/$1';
$route[MESSAGE_DOCTOR] = $doctor_path.'message/index';
$route[RESCHEDULEAPPOINTMENT_DOCTOR."/(:any)/(:any)/(:any)/(:any)"] = $doctor_path.'appointment/rescheduleAppointment/$1/$2/$3/$4';

$route[WALLET_DOCTOR] = $doctor_path.'wallet/index';
$route[BANK_DOCTOR] = $doctor_path.'wallet/bankDetail';
$route[DOCTORDOCUMENTREPORT] = $doctor_path.'documentReport/index';
$route[DOCTORDOCUMENTREPORTVIEW."/(:any)"] = $doctor_path.'documentReport/documentView/$1';



$route[REVIEW_DOCTOR] = $doctor_path.'myreview/index';

$route[PLANS_DOCTOR] = $doctor_path.'plans/index';

$route[VIDEOCALL_DOCTOR."/(:any)/(:any)"] = $doctor_path.'videocall/index/$1/$2';

$route[SERVICES_DOCTOR] = $doctor_path.'services/index';
$route[SERVICES_SHARE_DOCTOR."/(:any)/(:any)"] = $doctor_path.'services/shareService/$1/$2';

$route[DOCTOR_STRIPE] = 'auth/doctorStrip';

$admin = 'admin/';
$route['admin'] = $admin.'login/index';
$route['admin/changePassword'] = $admin.'admin/changePassword';
$route['admin/setting'] = $admin.'admin/setting';
$route['admin/login'] = $admin.'login/index';
$route['admin/logout'] = $admin.'login/logout';
$route['admin/forgotpassword'] = $admin.'login/forgotpassword';
$route['admin/verifyforgetcode/(:any)'] = $admin.'login/verifyforgetcode/$1';
$route['admin/manage-user'] = $admin.'user/index';

$doctor = 'HealthProfessionals/';
$route['my-account'] = $doctor.'/Dashboard/myAccount';

$route['(:any)'] = 'home/index/$1';