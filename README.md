# salesforce-trello-synchronisation

<h4>Issue</h4>
<p>At one of my clients SalesForce (SF) is used as the primary communication medium between the company and the external clients (brands and retailers).  Trello is used internally within the Tech/PM Team and shared by other departments.  Trello is used for more detailed discussions which are not essential to be communicated on the SalesForce cases.   
There was one member dedicated for synchronization of the communication between the two tools.</p>

<h4>Solution</h4>
<p>To fill these gaps, I built a SalesForce (SF) and trello integration script that creates cards on trello whenever a new case comes through into SF and adds all the comments.  Plus allows pushing comments from the trello to SF</p>


<h4>Actions performed by the Bot</h4> 

<ul>
  <li>The Sync happens based on the SF case number present on the Trello Card title.  If the trello card already contains a title with the case number, it would unarchive it otherwise it would create a new one. </li>
<li>New Trello Card (Support Board > Backlog ) will be created (with 4 hours due date) for every New Case in the business automatically ON TRELLO.  Will skip if already existing. </li>
<li>The New Case will be changed to assigned to department and department Tech ON SF</li>
<li>Any Closed Case RE-Opened with in the Tech HUB will be put to under review ON SF</li>
<li>Closed Case Card will be UnArchived ON TRELLO (irrespective of which board or list)</li>
<li>All the Comments from Salesforce will be added to TRELLO</li>
<li>But Not All the Comments from TRELLO will be added to Salesforce... Only the ones which have a '#publish' variable on them...</li>
<li>Any Under Review, Assigned to Department OR Further Action Required Card (which usually comes through directly to the Tech Department without going through the New Case Queue) will either un-archive a card if it existed in the past or creates a new card on TRELLO.</li>
<li>The Due Date is assigned to all the trello cards by using the Salseforce SLA DateTime. </li>
<li>The latest email from external client will be added to the Trello Card.</li>

</ul>
