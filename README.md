# salesforce-trello-synchronisation
I've initiated and built a SalesForce (SF) and trello integration script that creates cards on trello whenever a new case comes through into SF and adds all the comments.  Plus allows pushing comments from the trello to SF


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
