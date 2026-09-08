<?php
/*
==============================================
=============== Controllers ==================
==============================================
*/
$lang['ctrl_patient'] = 'Add Recipient/Donor';
$lang['ctrl_mrp'] = 'Add MRP/Lab';
$lang['ctrl_waiting_list'] = 'Wait list';
$lang['ctrl_update_patient'] = 'Update Patient';
$lang['ctrl_pairs'] = 'Pairs';
$lang['ctrl_dashboard'] = 'Dashboard';
$lang['ctrl_'] = '';


    {
        // -------------- Confirmations --------------
        $lang['ctrl_confirmation_add'] = '✅ Patient was added successfully.';
        $lang['ctrl_confirmation_update'] = '✅ Patient was updated successfully.';
        $lang['ctrl_confirmation_lab_add'] = '✅ Lab was added successfully.';
        $lang['ctrl_confirmation_mrp_add'] = '✅ MRP was added successfully.';

        // -------------- Errors --------------
        $lang['ctrl_error_missing'] = '❌ Some required fields are missing.';
        $lang['ctrl_error_missing_db'] = '❌ Could not find patient in db.';
        $lang['ctrl_error_too_large'] = '❌ MRN is too large.';
        $lang['ctrl_error_duplicate_mrn'] = '❌ This patient\'s mrn already exists.';
    }

/* 
==============================================
=================== Views ====================
==============================================
*/
    {
        // -------------- Tables --------------
        $lang['table_pair_no'] = 'Pair #';
        $lang['table_no'] = '#';
        $lang['table_mrn'] = 'MRN';
        $lang['table_name'] = 'Name';
        $lang['table_age'] = 'Age';
        $lang['table_type'] = 'Type';
        $lang['table_relation'] = 'Relationship';
        $lang['table_blood_group'] = 'Blood Group';
        $lang['table_status'] = 'Status';
        $lang['table_mrp'] = 'MRP';
        $lang['table_gender'] = 'Gender';
        $lang['table_phone_number'] = 'Phone Number';
        $lang['table_dialysis'] = 'Dialysis';
        $lang['table_entry_date'] = 'Entry Date';
        $lang['table_note'] = 'Note';
        $lang['table_score'] = 'Score';
        $lang['table_urgency'] = 'Urgent';
        $lang['table_urgency_0'] = 'Not Urgent';
        $lang['table_urgency_1'] = 'Urgent';
        $lang['table_not_applicable'] = 'N/A';
        $lang['table_match_status'] = 'Match Status';
        $lang['table_date_committee'] = 'Date of Committee';
        $lang['table_date_crossmatch'] = 'Date of Crossmatch';
        $lang['table_show_note'] = 'Show Note';
        $lang['table_lab_results'] = 'Lab Results';
        $lang['table_match_status_pending'] = 'Pending';
        $lang['table_match_status_confirmed'] = 'Confirmed';
        $lang['table_match_status_closed'] = 'Closed';
        $lang['table_match_status_completed'] = 'Completed';
        $lang['table_match_status_paired_exchange'] = 'Paired Exchange';

        // -------------- Pair --------------
        $lang['table_pair_field'] = 'Field';
        $lang['table_pair_patient_1'] = 'Patient 1';
        $lang['table_pair_patient_2'] = 'Patient 2';
    }

    {
        // -------------- Forms --------------
        $lang['form_mrn'] = 'MRN:';
        $lang['form_name'] = 'Name:';
        $lang['form_city'] = 'City:';
        $lang['form_phone_number'] = 'Phone Number:';
        $lang['form_gender'] = 'Gender:';
        $lang['form_age'] = 'Age:';
        $lang['form_mrp'] = 'MRP:';
        $lang['form_blood_group'] = 'Blood Group:';
        $lang['form_status'] = 'Status:';
        $lang['form_note'] = 'Notes:';
        $lang['form_recipient'] = 'Recipient';
        $lang['form_donor'] = 'Donor';
        $lang['form_dialysis'] = 'First Dialysis:';
        $lang['form_waiting_since'] = 'Entry Date:';
        $lang['form_urgency']       = 'Urgent?';
        $lang['form_urgent_1']        = 'Yes, it is urgent.';
        $lang['form_urgent_0']        = 'No, it is not urgent.';
        $lang['form_donor_true'] = 'has donor';
        $lang['form_donor_false'] = 'No donor';
        $lang['form_choose_recipient'] = 'Choose Recipient:';
        $lang['form_recipient_false'] = 'Yet To be Decided';
        $lang['form_match_status'] = 'Match Status:';
        $lang['form_relationship'] = 'Relationship:';
        $lang['form_pending'] = 'Pending';
        $lang['form_active'] = 'Active';
        $lang['form_on_hold'] = 'On Hold';
        $lang['form_declined'] = 'Declined';
        $lang['form_confirmed'] = 'Confirmed';
        $lang['form_closed'] = 'Closed';
        $lang['form_completed'] = 'Completed';
        $lang['form_paired_exchange'] = 'Paired Exchange';
        $lang['form_ready'] = 'Ready';
        $lang['form_donors_null'] = 'No Donors Found';
        $lang['form_choose_donor'] = 'Choose Donor:';
        $lang['form_M'] = 'Male';
        $lang['form_F'] = 'Female';
        $lang['form_blood_group_A'] = 'A';
        $lang['form_blood_group_AB'] = 'AB';
        $lang['form_blood_group_B'] = 'B';
        $lang['form_blood_group_O'] = 'O';
        $lang['form_submit'] = 'Submit';
        $lang['form_committee_date'] = 'Date of Committee:';
        $lang['form_crossmatch_date'] = 'Date of Crossmatch:';

        // -------------- Lab_Options --------------
        $lang['form_lab_option_ND'] = 'Not Done';
        $lang['form_lab_option_P'] = 'Pending';
        $lang['form_lab_option_D'] = 'Done';
        $lang['form_lab_option_PO'] = 'Positive';
        $lang['form_lab_option_NE'] = 'Negative';
        $lang['form_lab_option_AC'] = 'Acceptable';
        $lang['form_lab_option_AB'] = 'Abnormal';
        $lang['form_lab_option_NA'] = 'Not Applicable';
        $lang['form_lab_option_C'] = 'Cleared';
        $lang['form_lab_option_NC'] = 'Not Cleared';
        $lang['form_lab_option_G'] = 'Given';
        $lang['form_lab_option_NG'] = 'Not Given';
        $lang['form_lab_option_NR'] = 'Not Required';

        // -------------- Patient_Form --------------
        $lang['form_new_patient'] = 'New Patient';
        $lang['form_new_recipient'] = 'New Recipient';
        $lang['form_recipient_mrn'] = 'Recipient MRN:';
        $lang['form_recipient_name'] = 'Recipient Name:';
        $lang['form_recipient_city'] = 'Recipient City:';
        $lang['form_recipient_phone_number'] = 'Recipient Phone Number:';
        $lang['form_recipient_gender'] = 'Recipient Gender:';
        $lang['form_recipient_age'] = 'Recipient Age:';
        $lang['form_recipient_mrp'] = 'Recipient MRP:';
        $lang['form_recipient_coordinator'] = 'Recipient Coordinator:';
        $lang['form_recipient_blood_group'] = 'Recipient Blood Group:';
        $lang['form_recipient_status'] = 'Recipient Status:';
        $lang['form_recipient_note'] = 'Recipient Notes:';

        $lang['form_new_donor'] = 'New Donor';
        $lang['form_donor_mrn'] = 'Donor MRN:';
        $lang['form_donor_name'] = 'Donor Name:';
        $lang['form_donor_city'] = 'Donor City:';
        $lang['form_donor_phone_number'] = 'Donor Phone Number:';
        $lang['form_donor_gender'] = 'Donor Gender:';
        $lang['form_donor_age'] = 'Donor Age:';
        $lang['form_donor_mrp'] = 'Donor MRP:';
        $lang['form_donor_coordinator'] = 'Donor Coordinator:';
        $lang['form_donor_blood_group'] = 'Donor Blood Group:';
        $lang['form_donor_status'] = 'Donor Status:';
        $lang['form_donor_note'] = 'Donor Notes:';

        $lang['form_new_pair'] = 'New Pair';

        // -------------- MRP_Form --------------
        $lang['form_new_mrp'] = 'New MRP';
        $lang['form_mrp_id'] = 'MRP ID:';
        $lang['form_new_lab'] = 'New Lab';
        $lang['form_lab_name'] = 'Lab Name:';
        $lang['form_lab_shape'] = 'Shape of Stored data';
        $lang['form_numerical'] = 'Numbers';
        $lang['form_mrp_status'] = 'Done/Not Done';

        // -------------- Update_Form --------------
        $lang['form_update_patient'] = 'Update Patient';
        $lang['form_assigned_recipient'] = ' | Current Assigned Recipient';
        $lang['form_assigned_donor'] = ' | Current Assigned Donor';
        $lang['form_choose_mrp'] = 'Choose MRP';
        $lang['form_choose_coordinator'] = 'Choose Coordinator';
        $lang['form_labs_header'] = 'Lab Results';
        $lang['form_lab_status_done'] = 'Done';
        $lang['form_lab_status_not_done'] = 'Not Done';
        $lang['form_lab_status_pending'] = 'Pending';
        $lang['form_lab_status_na'] = 'N/A';
        $lang['form_lab_status_none'] = 'Choose';
    }

    {
        // -------------- Filters --------------
        $lang['filter_recipient'] = 'Recipient';
        $lang['filter_donor'] = 'Donor';
        $lang['filter_pending'] = 'Pending';
        $lang['filter_ready'] = 'Ready';
        $lang['filter_mrn'] = 'Choose by MRN:';
        $lang['filter_status_pending'] = 'Pending';
        $lang['filter_status_confirmed'] = 'Confirmed';
        $lang['filter_status_closed'] = 'Closed';
        $lang['filter_status_completed'] = 'Completed';
        $lang['filter_status_paired_exchange'] = 'Paired Exchange';
        $lang['filter_blood_group_A'] = 'A';
        $lang['filter_blood_group_AB'] = 'AB';
        $lang['filter_blood_group_B'] = 'B';
        $lang['filter_blood_group_O'] = 'O';


        // -------------- Organs --------------
        $lang['filter_organ_welcome'] = 'Please Choose an Organ to Begin';
        $lang['filter_organ_kidney'] = 'Kidney';
        $lang['filter_organ_liver'] = 'Liver';

        // -------------- Programs --------------
        $lang['filter_program_R_LRD'] = 'Living Related Donor';
        $lang['filter_program_R_LURD'] = 'Living Unrelated Donor';
        $lang['filter_program_R_DD'] = 'Deceased Donor';
        $lang['filter_program_R_PE'] = 'Paired Exchange';
        $lang['filter_program_D_D'] = 'No Recipient';
    }