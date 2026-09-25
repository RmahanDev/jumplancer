Option Compare Database
Option Explicit

' ====================================================================
'  Jump Lancer - Access database schema builder (v2)
'  Run BuildJumpLancer (cursor inside the Sub, press F5) to (re)create
'  the .accdb at OUTPUT_PATH. Re-running overwrites that file, so edit
'  this module for each revision and run it again.
'
'  v2 changes: employer plans (3 free projects, then a paid plan),
'  freelancer platform fee (20%, +5% with mentoring) on contracts,
'  a support-ticket system for mentorship requests (technical = ticket
'  only, motivational = ticket or phone), removed the hard readiness
'  gate on projects (it is now advisory only, shown to the freelancer).
' ====================================================================
Private Const OUTPUT_DIR As String = "C:\Users\mahan\Documents\GitHub\jumplancer\docs\database\"
Private Const OUTPUT_FILE As String = "jumplancer_db_v2.accdb"

Private db As DAO.Database
Private td As DAO.TableDef
Private descs As Collection
Private stepName As String
Private tblCount As Long
Private relCount As Long

Public Sub BuildJumpLancer()
    On Error GoTo Fail
    tblCount = 0: relCount = 0
    stepName = "create file"
    If Dir(OUTPUT_DIR, vbDirectory) = "" Then MkDir OUTPUT_DIR
    If Dir(OUTPUT_DIR & OUTPUT_FILE) <> "" Then Kill OUTPUT_DIR & OUTPUT_FILE
    Set db = DBEngine.CreateDatabase(OUTPUT_DIR & OUTPUT_FILE, dbLangGeneral, dbVersion120)

    ' ======================= IDENTITY & ROLES =======================
    T "roles": PK
    F "name", dbText, 50, True, , , "Role key: admin / employer / freelancer / mentor"
    F "display_name", dbText, 100, True, , , "Human-readable label"
    Idx "ux_roles_name", "name", True
    Save "Platform roles. One user can hold several roles through role_user."

    T "users": PK
    F "name", dbText, 100, True, , , "Full name"
    F "email", dbText, 150, True, , , "Login email (unique)"
    F "phone", dbText, 20, , , , "Mobile number for SMS OTP (unique when set)"
    F "password", dbText, 255, True, , , "Bcrypt hash - never plain text"
    F "avatar_path", dbText, 255
    F "bio", dbMemo
    F "status", dbText, 20, True, Q("active"), L("active,suspended,banned"), "Account state"
    F "email_verified_at", dbDate
    F "phone_verified_at", dbDate
    F "last_login_at", dbDate
    Call TS: SoftDel
    Idx "ux_users_email", "email", True
    Idx "ux_users_phone", "phone", True
    Save "Every person on the platform: employers, freelancers, mentors and admins."

    T "role_user"
    F "user_id", dbLong, , True
    F "role_id", dbLong, , True
    F "assigned_at", dbDate, , True, "Now()"
    Idx "PrimaryKey", "user_id,role_id", True, True
    Save "Pivot: which roles each user holds (maps to spatie/laravel-permission)."

    T "freelancer_profiles": PK
    F "user_id", dbLong, , True, , , "Owner user (1:1)"
    F "headline", dbText, 150, , , , "Short title, e.g. Junior Laravel Developer"
    F "level", dbText, 20, True, Q("beginner"), L("beginner,junior,intermediate,senior")
    F "readiness_score", dbInteger, , True, "0", "Between 0 And 100", "0-100. Does not block applying; a skill mismatch just warns the freelancer and lowers their rank to employers"
    F "hourly_rate", dbCurrency, , , , ">=0", "Toman"
    F "portfolio_url", dbText, 255
    F "availability", dbText, 20, True, Q("available"), L("available,busy,unavailable")
    F "onboarding_completed", dbBoolean, , , "No", , "Finished the beginner onboarding path"
    TS
    Idx "ux_freelancer_profiles_user", "user_id", True
    Save "Freelancer-specific data for users with the freelancer role."

    T "employer_profiles": PK
    F "user_id", dbLong, , True, , , "Owner user (1:1)"
    F "company_name", dbText, 150
    F "company_size", dbText, 20, , , L("solo,2-10,11-50,51-200,200+")
    F "industry", dbText, 100
    F "website", dbText, 255
    F "open_to_beginners", dbBoolean, , , "Yes", , "Agreed to hire beginners with mentor support"
    F "free_projects_used", dbByte, , True, "0", "Between 0 And 3", "Counts toward the 3 free project posts before a plan is required"
    TS
    Idx "ux_employer_profiles_user", "user_id", True
    Save "Employer-specific data for users with the employer role."

    T "plans": PK
    F "name", dbText, 100, True, , , "Plan name shown to employers"
    F "price", dbCurrency, , True, , ">=0", "Toman"
    F "project_quota", dbInteger, , , , ">=0", "Projects allowed during the plan period (empty = unlimited)"
    F "duration_days", dbInteger, , , , ">0", "Empty = does not expire"
    F "description", dbMemo
    F "is_active", dbBoolean, , , "Yes"
    TS
    Save "Paid plans employers buy once their 3 free projects are used."

    T "employer_subscriptions": PK
    F "employer_id", dbLong, , True
    F "plan_id", dbLong, , True
    F "projects_used", dbInteger, , True, "0", ">=0", "Projects posted under this subscription"
    F "status", dbText, 20, True, Q("active"), L("active,expired,cancelled")
    F "started_at", dbDate, , True, "Now()"
    F "expires_at", dbDate
    Save "An employer's purchase of a plan."

    T "mentor_profiles": PK
    F "user_id", dbLong, , True, , , "Owner user (1:1)"
    F "expertise_summary", dbMemo
    F "years_experience", dbInteger, , , "0", ">=0"
    F "mentoring_style", dbText, 20, True, Q("both"), L("technical,motivational,both")
    F "max_mentees", dbInteger, , True, "5", ">=0", "Capacity limit"
    F "is_verified", dbBoolean, , , "No", , "Vetted by the Jump Lancer team"
    F "is_volunteer", dbBoolean, , , "Yes", , "Community volunteer vs. paid staff mentor"
    TS
    Idx "ux_mentor_profiles_user", "user_id", True
    Save "Mentor-specific data. Mentors guide both freelancers and employers."

    ' ======================= CATALOG =======================
    T "categories": PK
    F "parent_id", dbLong, , , , , "Parent category (empty = top level)"
    F "name", dbText, 100, True
    F "slug", dbText, 120, True, , , "URL key (unique)"
    F "sort_order", dbInteger, , True, "0"
    Idx "ux_categories_slug", "slug", True
    Save "Two-level category tree for projects, skills and learning content."

    T "skills": PK
    F "category_id", dbLong, , True
    F "name", dbText, 100, True
    F "slug", dbText, 120, True
    Idx "ux_skills_slug", "slug", True
    Save "Skills that freelancers have and projects require."

    T "skill_user"
    F "user_id", dbLong, , True
    F "skill_id", dbLong, , True
    F "level", dbText, 20, True, Q("beginner"), L("beginner,intermediate,advanced")
    F "is_verified", dbBoolean, , , "No", , "Confirmed by passing an assessment"
    Idx "PrimaryKey", "user_id,skill_id", True, True
    Save "Pivot: skills of each freelancer."

    ' ======================= MARKETPLACE =======================
    T "projects": PK
    F "employer_id", dbLong, , True, , , "User who posted the project"
    F "category_id", dbLong, , True
    F "mentor_id", dbLong, , , , , "Mentor supervising this project (optional)"
    F "title", dbText, 200, True
    F "description", dbMemo, , True
    F "budget_type", dbText, 10, True, Q("fixed"), L("fixed,hourly")
    F "budget_min", dbCurrency, , , , ">=0", "Toman"
    F "budget_max", dbCurrency, , , , ">=0", "Toman"
    F "status", dbText, 20, True, Q("draft"), L("draft,pending_review,open,in_progress,completed,cancelled"), "pending_review = team checks it is beginner-suitable"
    F "is_beginner_friendly", dbBoolean, , , "Yes"
    F "deadline", dbDate
    F "published_at", dbDate
    Call TS: SoftDel
    Idx "ix_projects_status", "status"
    Save "Jobs posted by employers."

    T "project_skill"
    F "project_id", dbLong, , True
    F "skill_id", dbLong, , True
    Idx "PrimaryKey", "project_id,skill_id", True, True
    Save "Pivot: skills required by each project."

    T "proposals": PK
    F "project_id", dbLong, , True
    F "freelancer_id", dbLong, , True
    F "cover_letter", dbMemo, , True
    F "proposed_price", dbCurrency, , True, , ">=0", "Toman"
    F "delivery_days", dbInteger, , True, , ">0"
    F "status", dbText, 20, True, Q("pending"), L("draft,pending,shortlisted,accepted,rejected,withdrawn")
    F "mentor_reviewed_by", dbLong, , , , , "Mentor who reviewed the proposal before sending"
    F "mentor_feedback", dbMemo, , , , , "Mentor tips on the proposal"
    TS
    Idx "ux_proposals_project_freelancer", "project_id,freelancer_id", True
    Save "Freelancer bids on projects. One proposal per freelancer per project."

    T "contracts": PK
    F "project_id", dbLong, , True
    F "proposal_id", dbLong, , True
    F "employer_id", dbLong, , True
    F "freelancer_id", dbLong, , True
    F "mentor_id", dbLong, , , , , "Mentor supporting delivery (optional)"
    F "amount", dbCurrency, , True, , ">=0", "Total agreed amount (Toman)"
    F "mentorship_included", dbBoolean, , , "No", , "Mentor is supporting this contract's delivery"
    F "fee_percent", dbInteger, , True, "20", "Between 0 And 100", "Platform cut of freelancer earnings; 20, or 25 when mentorship_included"
    F "status", dbText, 20, True, Q("active"), L("active,completed,cancelled,disputed")
    F "started_at", dbDate
    F "completed_at", dbDate
    TS
    Idx "ux_contracts_proposal", "proposal_id", True
    Save "Agreement created when an employer accepts a proposal."

    T "milestones": PK
    F "contract_id", dbLong, , True
    F "title", dbText, 200, True
    F "description", dbMemo
    F "amount", dbCurrency, , True, , ">=0", "Toman"
    F "due_date", dbDate
    F "status", dbText, 20, True, Q("pending"), L("pending,funded,submitted,approved,released,refunded"), "funded = money held in escrow"
    F "sort_order", dbInteger, , True, "0"
    TS
    Save "Escrow payment steps inside a contract."

    T "wallets": PK
    F "user_id", dbLong, , True
    F "balance", dbCurrency, , True, "0", , "Withdrawable balance (Toman)"
    F "held_balance", dbCurrency, , True, "0", , "Money locked in escrow (Toman)"
    TS
    Idx "ux_wallets_user", "user_id", True
    Save "One wallet per user for deposits, escrow and payouts."

    T "transactions": PK
    F "wallet_id", dbLong, , True
    F "contract_id", dbLong
    F "milestone_id", dbLong
    F "subscription_id", dbLong, , , , , "Set when type=plan_purchase"
    F "type", dbText, 20, True, , L("deposit,escrow_hold,escrow_release,payout,refund,fee,plan_purchase,mentor_payout")
    F "amount", dbCurrency, , True, , , "Toman"
    F "gateway", dbText, 30, , , , "zarinpal, idpay, internal ..."
    F "gateway_ref", dbText, 100, , , , "Tracking code returned by the gateway"
    F "status", dbText, 20, True, Q("pending"), L("pending,succeeded,failed,cancelled")
    F "description", dbText, 255
    F "created_at", dbDate, , True, "Now()"
    Save "Money ledger. Never edit amounts - add a new row instead."

    T "reviews": PK
    F "contract_id", dbLong, , True
    F "reviewer_id", dbLong, , True
    F "reviewee_id", dbLong, , True
    F "rating", dbByte, , True, , "Between 1 And 5"
    F "comment", dbMemo
    F "created_at", dbDate, , True, "Now()"
    Idx "ux_reviews_contract_reviewer", "contract_id,reviewer_id", True
    Save "Two-way reviews after a contract ends."

    T "disputes": PK
    F "contract_id", dbLong, , True
    F "raised_by", dbLong, , True
    F "reason", dbMemo, , True
    F "status", dbText, 20, True, Q("open"), L("open,under_review,resolved,rejected")
    F "resolved_by", dbLong, , , , , "Admin who resolved it"
    F "resolution_note", dbMemo
    F "created_at", dbDate, , True, "Now()"
    F "resolved_at", dbDate
    Save "Conflicts between employer and freelancer, handled by admins."

    ' ======================= MENTORING =======================
    T "tickets": PK
    F "requester_id", dbLong, , True, , , "Freelancer or employer asking for mentoring"
    F "ticket_type", dbText, 20, True, Q("technical"), L("technical,motivational"), "Technical mentoring is always handled by ticket; motivational can also use a phone call"
    F "channel", dbText, 10, True, Q("ticket"), L("ticket,phone"), "How the request is handled. Must be 'ticket' when ticket_type=technical"
    F "phone_number", dbText, 20, , , , "Required when channel=phone"
    F "subject", dbText, 200, True
    F "message", dbMemo, , True
    F "status", dbText, 20, True, Q("open"), L("open,assigned,in_progress,closed")
    F "assigned_mentor_id", dbLong, , , , , "Set once a mentor picks up the ticket"
    F "created_at", dbDate, , True, "Now()"
    F "closed_at", dbDate
    Save "Every mentoring request starts here. All technical requests come in as tickets; motivational requests can be a ticket or a phone call."

    T "mentorship_programs": PK
    F "ticket_id", dbLong, , , , , "Ticket that started this relationship"
    F "mentor_id", dbLong, , True
    F "mentee_id", dbLong, , True, , , "Freelancer or employer being mentored"
    F "track", dbText, 20, True, Q("freelancer"), L("freelancer,employer"), "Which side of the market is mentored"
    F "goal", dbMemo
    F "status", dbText, 20, True, Q("active"), L("active,paused,completed,cancelled")
    F "started_at", dbDate
    F "ended_at", dbDate
    TS
    Save "A mentor-mentee relationship, created once a ticket is assigned - the core Jump Lancer differentiator."

    T "mentorship_sessions": PK
    F "program_id", dbLong, , True
    F "scheduled_at", dbDate, , True
    F "duration_minutes", dbInteger, , True, "30", ">0"
    F "session_type", dbText, 20, True, Q("technical"), L("technical,motivational,review")
    F "status", dbText, 20, True, Q("scheduled"), L("scheduled,done,missed,cancelled")
    F "meeting_link", dbText, 255
    F "mentor_notes", dbMemo
    F "mentee_rating", dbByte, , , , "Between 1 And 5", "Mentee feedback on the session"
    F "created_at", dbDate, , True, "Now()"
    Save "Individual meetings inside a mentorship program."

    T "assessments": PK
    F "skill_id", dbLong, , True
    F "title", dbText, 200, True
    F "description", dbMemo
    F "pass_score", dbInteger, , True, "70", "Between 0 And 100"
    F "time_limit_minutes", dbInteger
    F "is_active", dbBoolean, , , "Yes"
    F "created_at", dbDate, , True, "Now()"
    Save "Skill tests used to measure a freelancer's readiness."

    T "assessment_attempts": PK
    F "assessment_id", dbLong, , True
    F "user_id", dbLong, , True
    F "score", dbInteger, , , , "Between 0 And 100"
    F "passed", dbBoolean, , , "No"
    F "started_at", dbDate, , True, "Now()"
    F "finished_at", dbDate
    Save "Each time a user takes an assessment."

    T "learning_contents": PK
    F "author_id", dbLong, , True, , , "Usually a mentor or admin"
    F "category_id", dbLong
    F "title", dbText, 200, True
    F "content_type", dbText, 20, True, Q("article"), L("article,video,checklist,roadmap")
    F "audience", dbText, 20, True, Q("freelancer"), L("freelancer,employer,all")
    F "purpose", dbText, 20, True, Q("technical"), L("technical,motivational")
    F "body", dbMemo
    F "media_url", dbText, 255
    F "is_published", dbBoolean, , , "No"
    F "published_at", dbDate
    TS
    Save "Articles, videos and roadmaps that support mentoring."

    T "badges": PK
    F "code", dbText, 50, True
    F "name", dbText, 100, True
    F "description", dbText, 255
    F "icon_path", dbText, 255
    Idx "ux_badges_code", "code", True
    Save "Achievements that motivate beginners."

    T "badge_user"
    F "user_id", dbLong, , True
    F "badge_id", dbLong, , True
    F "awarded_at", dbDate, , True, "Now()"
    Idx "PrimaryKey", "user_id,badge_id", True, True
    Save "Pivot: badges earned by each user."

    ' ======================= MESSAGING =======================
    T "conversations": PK
    F "project_id", dbLong, , , , , "Set when the chat is about a project"
    F "contract_id", dbLong
    F "mentorship_program_id", dbLong
    F "created_at", dbDate, , True, "Now()"
    Save "Chat threads (project, contract or mentorship)."

    T "conversation_participants"
    F "conversation_id", dbLong, , True
    F "user_id", dbLong, , True
    F "joined_at", dbDate, , True, "Now()"
    F "last_read_at", dbDate
    Idx "PrimaryKey", "conversation_id,user_id", True, True
    Save "Who is in each conversation."

    T "messages": PK
    F "conversation_id", dbLong, , True
    F "sender_id", dbLong, , True
    F "body", dbMemo
    F "attachment_path", dbText, 255
    F "created_at", dbDate, , True, "Now()"
    Save "Chat messages."

    ' ======================= RELATIONSHIPS =======================
    ' R parent, child, foreign_key, cascadeDelete
    R "users", "role_user", "user_id", True
    R "roles", "role_user", "role_id"
    R "users", "freelancer_profiles", "user_id", True
    R "users", "employer_profiles", "user_id", True
    R "users", "mentor_profiles", "user_id", True
    R "categories", "categories", "parent_id"
    R "categories", "skills", "category_id"
    R "users", "skill_user", "user_id", True
    R "skills", "skill_user", "skill_id", True
    R "users", "projects", "employer_id"
    R "categories", "projects", "category_id"
    R "users", "projects", "mentor_id"
    R "projects", "project_skill", "project_id", True
    R "skills", "project_skill", "skill_id", True
    R "projects", "proposals", "project_id", True
    R "users", "proposals", "freelancer_id"
    R "users", "proposals", "mentor_reviewed_by"
    R "projects", "contracts", "project_id"
    R "proposals", "contracts", "proposal_id"
    R "users", "contracts", "employer_id"
    R "users", "contracts", "freelancer_id"
    R "users", "contracts", "mentor_id"
    R "contracts", "milestones", "contract_id", True
    R "users", "employer_subscriptions", "employer_id"
    R "plans", "employer_subscriptions", "plan_id"
    R "users", "wallets", "user_id", True
    R "wallets", "transactions", "wallet_id"
    R "contracts", "transactions", "contract_id"
    R "milestones", "transactions", "milestone_id"
    R "employer_subscriptions", "transactions", "subscription_id"
    R "contracts", "reviews", "contract_id"
    R "users", "reviews", "reviewer_id"
    R "users", "reviews", "reviewee_id"
    R "contracts", "disputes", "contract_id"
    R "users", "disputes", "raised_by"
    R "users", "disputes", "resolved_by"
    R "users", "tickets", "requester_id"
    R "users", "tickets", "assigned_mentor_id"
    R "tickets", "mentorship_programs", "ticket_id"
    R "users", "mentorship_programs", "mentor_id"
    R "users", "mentorship_programs", "mentee_id"
    R "mentorship_programs", "mentorship_sessions", "program_id", True
    R "skills", "assessments", "skill_id"
    R "assessments", "assessment_attempts", "assessment_id", True
    R "users", "assessment_attempts", "user_id", True
    R "users", "learning_contents", "author_id"
    R "categories", "learning_contents", "category_id"
    R "users", "badge_user", "user_id", True
    R "badges", "badge_user", "badge_id", True
    R "projects", "conversations", "project_id"
    R "contracts", "conversations", "contract_id"
    R "mentorship_programs", "conversations", "mentorship_program_id"
    R "conversations", "conversation_participants", "conversation_id", True
    R "users", "conversation_participants", "user_id", True
    R "conversations", "messages", "conversation_id", True
    R "users", "messages", "sender_id"

    ' ======================= SEED DATA =======================
    X "INSERT INTO roles ([name], display_name) VALUES ('admin','Administrator')"
    X "INSERT INTO roles ([name], display_name) VALUES ('employer','Employer')"
    X "INSERT INTO roles ([name], display_name) VALUES ('freelancer','Freelancer')"
    X "INSERT INTO roles ([name], display_name) VALUES ('mentor','Mentor')"

    Cat "", "Programming & Tech", "programming-tech", 1
    Cat "", "Design & Creative", "design-creative", 2
    Cat "", "Writing & Translation", "writing-translation", 3
    Cat "", "Digital Marketing", "digital-marketing", 4
    Cat "programming-tech", "Web Development", "web-development", 1
    Cat "programming-tech", "Mobile Apps", "mobile-apps", 2
    Cat "design-creative", "UI/UX Design", "ui-ux", 1
    Cat "design-creative", "Graphic Design", "graphic-design", 2
    Cat "writing-translation", "Content Writing", "content-writing", 1
    Cat "writing-translation", "Translation", "translation", 2
    Cat "digital-marketing", "SEO", "seo", 1
    Cat "digital-marketing", "Social Media", "social-media", 2

    Sk "web-development", "PHP", "php"
    Sk "web-development", "Laravel", "laravel"
    Sk "web-development", "WordPress", "wordpress"
    Sk "web-development", "JavaScript", "javascript"
    Sk "web-development", "React", "react"
    Sk "web-development", "HTML & CSS", "html-css"
    Sk "mobile-apps", "Flutter", "flutter"
    Sk "ui-ux", "Figma", "figma"
    Sk "graphic-design", "Photoshop", "photoshop"
    Sk "content-writing", "Copywriting", "copywriting"
    Sk "translation", "English-Persian Translation", "en-fa-translation"
    Sk "seo", "Technical SEO", "technical-seo"
    Sk "social-media", "Instagram Marketing", "instagram-marketing"

    X "INSERT INTO badges (code, [name], description) VALUES ('first_proposal','First Step','Sent your first proposal')"
    X "INSERT INTO badges (code, [name], description) VALUES ('first_contract','First Deal','Won your first contract')"
    X "INSERT INTO badges (code, [name], description) VALUES ('first_five_star','Five Stars','Received your first 5-star review')"
    X "INSERT INTO badges (code, [name], description) VALUES ('market_ready','Market Ready','Reached a readiness score of 70+')"
    X "INSERT INTO badges (code, [name], description) VALUES ('mentorship_graduate','Graduate','Completed a mentorship program')"

    db.Close
    Set db = Nothing
    MsgBox "Jump Lancer database created:" & vbCrLf & OUTPUT_DIR & OUTPUT_FILE & vbCrLf & vbCrLf & _
           tblCount & " tables, " & relCount & " relationships.", vbInformation, "Jump Lancer"
    Exit Sub

Fail:
    MsgBox "Failed at: " & stepName & vbCrLf & Err.Number & " - " & Err.Description, vbCritical, "Jump Lancer"
    On Error Resume Next
    If Not db Is Nothing Then db.Close
    Set db = Nothing
End Sub

' ----------------------- helpers -----------------------
Private Sub T(nm As String)
    stepName = "table " & nm
    Set td = db.CreateTableDef(nm)
    Set descs = New Collection
End Sub

Private Sub PK()
    Dim fl As DAO.Field
    Set fl = td.CreateField("id", dbLong)
    fl.Attributes = dbAutoIncrField
    td.Fields.Append fl
    descs.Add "id|Primary key (AutoNumber)"
    Idx "PrimaryKey", "id", True, True
End Sub

Private Sub F(nm As String, typ As Integer, Optional sz As Long = 0, Optional req As Boolean = False, _
              Optional defVal As String = "", Optional rule As String = "", Optional desc As String = "")
    Dim fl As DAO.Field
    stepName = "field " & td.Name & "." & nm
    If typ = dbText Then
        If sz = 0 Then sz = 255
        Set fl = td.CreateField(nm, dbText, sz)
    Else
        Set fl = td.CreateField(nm, typ)
    End If
    If typ = dbText Or typ = dbMemo Then fl.AllowZeroLength = False
    fl.Required = req
    If defVal <> "" Then fl.DefaultValue = defVal
    If rule <> "" Then
        fl.ValidationRule = rule
        fl.ValidationText = nm & " must be: " & rule
    End If
    td.Fields.Append fl
    If desc <> "" Then descs.Add nm & "|" & desc
End Sub

Private Sub TS()
    F "created_at", dbDate, , True, "Now()", , "Row creation time"
    F "updated_at", dbDate, , , , , "Last update time"
End Sub

Private Sub SoftDel()
    F "deleted_at", dbDate, , , , , "Soft-delete marker (Laravel SoftDeletes)"
End Sub

Private Sub Idx(nm As String, flds As String, Optional uniq As Boolean = False, Optional prim As Boolean = False)
    Dim ix As DAO.Index, p() As String, i As Integer
    Set ix = td.CreateIndex(nm)
    p = Split(flds, ",")
    For i = 0 To UBound(p)
        ix.Fields.Append ix.CreateField(Trim(p(i)))
    Next i
    ix.Primary = prim
    ix.Unique = (uniq Or prim)
    td.Indexes.Append ix
End Sub

Private Sub Save(tableDesc As String)
    Dim itm As Variant, p() As String, tbl As DAO.TableDef
    stepName = "save " & td.Name
    db.TableDefs.Append td
    Set tbl = db.TableDefs(td.Name)
    SetProp tbl, "Description", tableDesc
    For Each itm In descs
        p = Split(itm, "|")
        SetProp tbl.Fields(p(0)), "Description", p(1)
    Next itm
    tblCount = tblCount + 1
End Sub

Private Sub SetProp(obj As Object, pName As String, pVal As String)
    On Error Resume Next
    obj.Properties(pName).Value = pVal
    If Err.Number <> 0 Then
        Err.Clear
        On Error GoTo 0
        obj.Properties.Append obj.CreateProperty(pName, dbText, pVal)
    End If
End Sub

Private Sub R(parentT As String, childT As String, childF As String, Optional cascadeDelete As Boolean = False)
    Dim rl As DAO.Relation, fl As DAO.Field, attr As Long
    stepName = "relation " & parentT & " -> " & childT & "." & childF
    If cascadeDelete Then attr = dbRelationDeleteCascade Else attr = 0
    Set rl = db.CreateRelation("fk_" & childT & "_" & childF, parentT, childT, attr)
    Set fl = rl.CreateField("id")
    fl.ForeignName = childF
    rl.Fields.Append fl
    db.Relations.Append rl
    relCount = relCount + 1
End Sub

Private Sub X(sql As String)
    stepName = "seed: " & Left(sql, 70)
    db.Execute sql, dbFailOnError
End Sub

Private Sub Cat(parentSlug As String, nm As String, slug As String, ord As Integer)
    If parentSlug = "" Then
        X "INSERT INTO categories ([name], slug, sort_order) VALUES ('" & nm & "','" & slug & "'," & ord & ")"
    Else
        X "INSERT INTO categories (parent_id, [name], slug, sort_order) SELECT id, '" & nm & "','" & slug & "'," & ord & _
          " FROM categories WHERE slug='" & parentSlug & "'"
    End If
End Sub

Private Sub Sk(catSlug As String, nm As String, slug As String)
    X "INSERT INTO skills (category_id, [name], slug) SELECT id, '" & nm & "','" & slug & "' FROM categories WHERE slug='" & catSlug & "'"
End Sub

Private Function Q(s As String) As String
    Q = """" & s & """"
End Function

Private Function L(vals As String) As String
    Dim p() As String, i As Integer, s As String
    p = Split(vals, ",")
    For i = 0 To UBound(p)
        If i > 0 Then s = s & ","
        s = s & """" & Trim(p(i)) & """"
    Next i
    L = "In (" & s & ")"
End Function
