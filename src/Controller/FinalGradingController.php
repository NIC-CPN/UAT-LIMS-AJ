<?php
namespace App\Controller;

use Cake\Event\Event;
use App\Network\Email\Email;
use Cake\ORM\Entity;
use Cake\Datasource\ConnectionManager;
use Cake\View;

class FinalGradingController extends AppController
{

	var $name 		= 'FinalGrading';

	public function beforeFilter($event) {
		parent::beforeFilter($event);

		$this->viewBuilder()->setLayout('admin_dashboard');
		$this->viewBuilder()->setHelpers(['Form','Html']);
		$this->loadComponent('Customfunctions');
		$this->loadComponent('Authentication');
	}

/********************************************************************************************************************************************************************************/

	//to validate login user
	public function authenticateUser() {

		$this->loadModel('DmiUserRoles');
		$user_access = $this->DmiUserRoles->find('all',array('conditions'=>array('user_email_id IS'=>$this->Session->read('username'))))->first();

		if (!empty($user_access)) {
			//proceed
		} else {
			$this->customAlertPage("Sorry.. You don't have permission to view this page");
			exit;
		}
	}

/********************************************************************************************************************************************************************************/

	public function availableForGradingToInward() {

		$this->authenticateUser();
		$result = $this->getSampleToGradeByInward();
		$this->set('sample_codes',$result[0]);	//add array for showing result[0] regular flow 01-08-2022
		$this->set('sample_codes1',$result[1]);	//add array for showing result[1] ilc flow 01-08-2022
	}

/********************************************************************************************************************************************************************************/


	//created common function to fetch list , to be used for dashboard counts also, on 28-04-2021 by Amol
	public function getSampleToGradeByInward() {

		$conn = ConnectionManager::get('default');
		$user_code = $_SESSION['user_code'];
		$this->loadModel('Workflow');

        //Why : To show the finalized test sample to inward officer if sample forward by Lab incharge officer, Done by pravin bhakare 16-08-2019 */

		$query = $conn->execute("SELECT ft.sample_code,ft.sample_code FROM Final_Test_Result AS ft
								  INNER JOIN workflow AS w ON ft.org_sample_code = w.org_sample_code
								  INNER JOIN m_sample_allocate sa ON ft.org_sample_code = sa.org_sample_code
								  INNER JOIN sample_inward AS si ON ft.org_sample_code = si.org_sample_code
								  WHERE si.sample_type_code != 9 AND ft.display ='Y' AND si.status_flag ='FR' AND w.stage_smpl_flag IN ('AR','FR') AND w.dst_usr_cd='$user_code' AND w.stage_smpl_cd NOT IN ('','blank')
								  GROUP BY ft.sample_code ");
								// stage_smpl_cd !='' condition added for empty sample code
								// by shankhpal shende on 19/04/2023

		$final_result_details = $query->fetchAll('assoc');

		//Conditions to check wheather stage sample code is final graded or not.
		$final_result = array();

		if (!empty($final_result_details)) {

			foreach ($final_result_details as $stage_sample_code) {

				$final_grading = $this->Workflow->find('all',array('conditions'=>array('stage_smpl_flag'=>'FG','stage_smpl_cd IS'=>$stage_sample_code['sample_code'],'src_usr_cd'=>$user_code)))->first();

				if (empty($final_grading)) {

					$final_result[]= $stage_sample_code;
				}
			}
		}

		//to be used in below core query format, that's why
		$arr = "IN(";
		foreach ($final_result as $each) {
			$arr .= "'";
			$arr .= $each['sample_code'];
			$arr .= "',";
		}

		$arr .= "'00')";//00 is intensionally given to put last value in string.


		/*  [IMPORTANT]
			This Below QUERY is modified to fetch the filtered and duplication.
			So it is modified and added GROUP clauses and AGGREGATION functions.
			- Akash [10-02-2023]
		$query = $conn->execute("SELECT w.stage_smpl_cd,
										si.received_date,
										st.sample_type_desc,
										mcc.category_name,
										mc.commodity_name,
										ml.ro_office,
										w.modified AS submitted_on
										FROM sample_inward AS si
								 INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
								 INNER JOIN m_commodity_category AS mcc ON si.category_code=mcc.category_code
								 INNER JOIN dmi_ro_offices AS ml ON ml.id=si.loc_id
								 INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
								 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
								 WHERE w.stage_smpl_cd ".$arr." AND w.stage_smpl_flag = 'AR' ORDER BY w.modified desc");
		*/

		$query = $conn->execute("SELECT DISTINCT w.stage_smpl_cd,
												MAX(si.received_date) as received_date,
												MAX(st.sample_type_desc) as sample_type_desc,
												MAX(mcc.category_name) as category_name,
												MAX(mc.commodity_name) as commodity_name,
												MAX(ml.ro_office) as ro_office,
												MAX(w.modified) AS submitted_on
								FROM sample_inward AS si
								INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
								INNER JOIN m_commodity_category AS mcc ON si.category_code=mcc.category_code
								INNER JOIN dmi_ro_offices AS ml ON ml.id=si.loc_id
								INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
								INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
								WHERE w.stage_smpl_cd ".$arr." AND w.stage_smpl_cd NOT IN ('','blank') AND w.stage_smpl_flag = 'AR'
								GROUP BY w.stage_smpl_cd
								ORDER BY submitted_on DESC");
				// stage_smpl_cd !='' condition added for empty sample code
				// by shankhpal shende on 19/04/20

		$result = $query->fetchAll('assoc');

		//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		// added for ilc flow Done By Shreeya
		$query1 = $conn->execute("SELECT ft.sample_code,ft.sample_code FROM Final_Test_Result AS ft
								  INNER JOIN workflow AS w ON ft.org_sample_code = w.org_sample_code
								  INNER JOIN m_sample_allocate sa ON ft.org_sample_code = sa.org_sample_code
								  INNER JOIN sample_inward AS si ON ft.org_sample_code = si.org_sample_code
								  WHERE si.sample_type_code = 9 AND ft.display ='Y' AND si.status_flag ='FR' AND w.stage_smpl_cd NOT IN ('','blank') AND w.stage_smpl_flag IN ('AR','FR') AND w.dst_usr_cd='$user_code'
								  GROUP BY ft.sample_code ");
					// stage_smpl_cd !='' condition added for empty sample code
					// by shankhpal shende on 19/04/20
		$final_result_details1 = $query1->fetchAll('assoc');


		//Conditions to check wheather stage sample code is final graded or not.
		$final_result1 = array();

		if (!empty($final_result_details1)) {

			foreach ($final_result_details1 as $stage_sample_code) {

				$final_grading1 = $this->Workflow->find('all',array('conditions'=>array('stage_smpl_flag'=>'FG','stage_smpl_cd IS'=>$stage_sample_code['sample_code'],'src_usr_cd'=>$user_code)))->first();

				if (empty($final_grading1)) {

					$final_result1[]= $stage_sample_code;
				}
			}
		}

		//to be used in below core query format, that's why
		$arr = "IN(";
		foreach ($final_result1 as $each) {
			$arr .= "'";
			$arr .= $each['sample_code'];
			$arr .= "',";
		}

		$arr .= "'00')";//00 is intensionally given to put last value in string.

		$query2 = $conn->execute("SELECT w.stage_smpl_cd,si.received_date,
									st.sample_type_desc,mcc.category_name,
									mc.commodity_name,ml.ro_office,w.modified AS submitted_on
								 FROM sample_inward AS si
								 INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
								 INNER JOIN m_commodity_category AS mcc ON si.category_code=mcc.category_code
								 INNER JOIN dmi_ro_offices AS ml ON ml.id=si.loc_id
								 INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
								 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
								 WHERE w.stage_smpl_cd NOT IN ('','blank') AND w.stage_smpl_cd ".$arr." AND w.stage_smpl_flag = 'AR'
								 ORDER BY w.modified DESC");
								// stage_smpl_cd !='' condition added for empty sample code
								// by shankhpal shende on 19/04/20
		$result1 = $query2->fetchAll('assoc');


		$newResult = array($result,$result1);
		return $newResult;

	}


/**************************************************************************************************************************************************************************/

	public function redirectToVerify($verify_sample_code){

		$this->Session->write('verify_sample_code',$verify_sample_code);
		$this->redirect(array('controller'=>'FinalGrading','action'=>'grading_by_inward'));
	}

/**************************************************************************************************************************************************************************/

	public function gradingByInward(){

		$this->authenticateUser();
		$this->viewBuilder()->setLayout('admin_dashboard');
		$str1		  = "";
		$this->loadModel('MCommodityCategory');
		$this->loadModel('DmiUsers');
		$this->loadModel('FinalTestResult');
		$this->loadModel('MGradeStandard');
		$this->loadModel('MTestMethod');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('MSampleAllocate');
		$this->loadModel('MCommodity');
		$this->loadModel('MGradeDesc');
		$this->loadModel('DmiRoOffices');

		$conn = ConnectionManager::get('default');

		$verify_sample_code = $this->Session->read('verify_sample_code');

		if (!empty($verify_sample_code)) {

			$this->set('samples_list',array($verify_sample_code=>$verify_sample_code));
			$this->set('stage_sample_code',$verify_sample_code);//for hidden field, to use common script

			$grades_strd=$this->MGradeStandard->find('list',array('keyField'=>'grd_standrd','valueField'=>'grade_strd_desc','order' => array('grade_strd_desc' => 'ASC')))->toArray();
			$this->set('grades_strd',$grades_strd);

			$grades=$this->MGradeDesc->find('list',array('keyField'=>'grade_code','valueField'=>'grade_desc','order' => array('grade_desc' => 'ASC'),'conditions' => array('display' => 'Y')))->toArray();
			$this->set('grades',$grades);

			if ($this->request->is('post')) {

				$postdata = $this->request->getData();
				//html encode the each post inputs
				foreach($postdata as $key => $value){
					$postdata[$key] = htmlentities($this->request->getData($key), ENT_QUOTES);
				}

				if ($this->request->getData('button')=='add') {

					// Add new filed to add subgrading value
					$subGradeChecked = $this->request->getData('subgrade');

					$sample_code=$this->request->getData('sample_code');
					$category_code=$this->request->getData('category_code');
					$commodity_code=$this->request->getData('commodity_code');
					$remark=$this->request->getData('remark');

					if (null !== ($this->request->getData('result_flg'))) {
						$result_flg	= $this->request->getData('result_flg');
					} else {
						$result_flg="";
					}

					$flagArr = array("P", "F", "M","R");

					$result_grade	=	'';
					$grade_code_vs=$this->request->getData('grade_code');

					$tran_date=$this->request->getData("tran_date");
					$ogrsample1= $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $sample_code)))->first();
					$ogrsample=$ogrsample1['org_sample_code'];

					$src_usr_cd = $conn->execute("SELECT src_usr_cd FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='TA' ");
					$src_usr_cd = $src_usr_cd->fetchAll('assoc');
					$abc = $src_usr_cd[0]['src_usr_cd'];

					$test_n_r_no = $conn->execute("SELECT max(test_n_r_no) FROM m_sample_allocate WHERE sample_code='$sample_code' AND test_n_r='R' ");
					$test_n_r_no = $test_n_r_no->fetchAll('assoc');
					$abc1 = $test_n_r_no[0]['max']+1;

					if ($result_flg=='R') {

						$_SESSION["loc_id"] =$_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] =$_SESSION["user_code"];

						$workflow_data = array("org_sample_code"=>$ogrsample,
												"src_loc_id"=>$_SESSION["posted_ro_office"],
												"src_usr_cd"=>$_SESSION["user_code"],
												"dst_loc_id"=>$_SESSION["posted_ro_office"],
												"dst_usr_cd"=>$abc,"stage_smpl_flag"=>"R",
												"tran_date"=>$tran_date,
												"user_code"=>$_SESSION["user_code"],
												"stage_smpl_cd"=>$sample_code,  "stage"=>"8");

						$workflowEntity =  $this->Workflow->newEntity($workflow_data);

						$this->Workflow->save($workflowEntity);


						$dst_usr_cd = $conn->execute("SELECT dst_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='R' ");
						$dst_usr_cd = $dst_usr_cd->fetchAll('assoc');

						$abcd = $dst_usr_cd[0]['dst_usr_cd'];

						$user_name = $conn->execute("SELECT DISTINCT role FROM dmi_users AS u
													 INNER JOIN workflow AS w ON u.id = w.dst_usr_cd
													 INNER JOIN user_role AS r ON u.role = r.role_name
													 WHERE dst_usr_cd ='$abcd'
													 AND org_sample_code='$ogrsample'
													 AND stage_smpl_flag='R'");

						$user_name = $user_name->fetchAll('assoc');

						$abc2 = $user_name[0]['role'];

						$_SESSION["loc_id"] =$_SESSION["posted_ro_office"];

						$_SESSION["loc_user_id"] =$_SESSION["user_code"];

						$date=date("Y/m/d");

						$sample_code=trim($this->request->getData('sample_code'));

						$query = $conn->execute("SELECT si.org_sample_code
												 FROM sample_inward AS si
												 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												 WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($result_flg =='F') {

							$result_flg='Fail';

						} elseif ($result_flg=='M') {

							$result_flg='Misgrade';

						} else {

							$result_flg='SR';
						}

						// Add two new fileds to add subgrading value and inward grading date ,

						$conn->execute("UPDATE sample_inward
										SET remark ='$remark', status_flag ='R', grade ='$grade_code_vs',
										grading_date ='$date', inward_grading_date = '$date',
										sub_grad_check_iwo = '$subGradeChecked', inward_grade = '$grade_code_vs'
										WHERE category_code = '$category_code'
										AND commodity_code = '$commodity_code'
										AND org_sample_code = '$ogrsample_code'
										AND display = 'Y' ");

						$oic = $this->DmiRoOffices->getOfficeIncharge($_SESSION["posted_ro_office"]);

						#SMS: Sample Marked For Retest By Inward
						$this->DmiSmsEmailTemplates->sendMessage(114,$abc,$sample_code); 			  #INWARD
						$this->DmiSmsEmailTemplates->sendMessage(113,$oic,$sample_code); 			  #OIC
						$this->LimsUserActionLogs->saveActionLog('Sample Sent For Retest','Success'); #Action

						echo '#The sample is marked for retest and re-sent to '.$abc2.'#';

						exit;

					} else {

						$dst_loc =$_SESSION["posted_ro_office"];

						if ($_SESSION['user_flag']=='RAL') {

							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];

						} else {

							/* Change the conditions for to find destination user id, after test result approved by lab inward officer the application send to RAL/CAL OIC officer */
							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];
						}


						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

								$workflow_data = array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"FS",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"8");
							} else {

								// Change the stage_smpl_flag value FG to FGIO to genreate the sample report after grading by OIC,
								$workflow_data = array("org_sample_code"=>$ogrsample,
													"src_loc_id"=>$_SESSION["posted_ro_office"],
													"src_usr_cd"=>$_SESSION["user_code"],
													"dst_loc_id"=>$_SESSION["posted_ro_office"],
													"dst_usr_cd"=>$dst_usr,
													"stage_smpl_flag"=>"FGIO",
													"tran_date"=>$tran_date,
													"user_code"=>$_SESSION["user_code"],
													"stage_smpl_cd"=>$sample_code,
													"stage"=>"8");
							}

						} elseif ($_SESSION['user_flag']=='CAL') {

							if (trim($result_flg)=='F') {

								$workflow_data =  array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"FC",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"7");

							} else {

								$workflow_data =  array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"VS",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"7");
							}
						}

						$workflowEntity = $this->Workflow->newEntity($workflow_data);

						$this->Workflow->save($workflowEntity);

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];

						$_SESSION["loc_user_id"] = $_SESSION["user_code"];

						$date = date("Y/m/d");

						$sample_code = trim($this->request->getData('sample_code'));

						$query = $conn->execute("SELECT si.org_sample_code
												 FROM sample_inward AS si
												 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												 WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET
														status_flag='FS',
														remark ='$remark',
														grade='$grade_code_vs',
														grading_date='$date',
														inward_grading_date='$date',
														sub_grad_check_iwo='$subGradeChecked',
														inward_grade='$grade_code_vs',
														grade_user_cd=".$_SESSION['user_code'].",
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														ral_anltc_rslt_rcpt_dt='$tran_date'
												WHERE category_code= '$category_code'
												AND commodity_code = '$commodity_code'
												AND org_sample_code = '$ogrsample_code'
												AND display = 'Y' ");

							} else {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET
														status_flag='FG',
														remark ='$remark',
														grade='$grade_code_vs',
														grading_date='$date',
														inward_grading_date='$date',
														sub_grad_check_iwo='$subGradeChecked',
														inward_grade='$grade_code_vs',
														grade_user_cd=".$_SESSION['user_code'].",
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														ral_anltc_rslt_rcpt_dt='$tran_date'
												WHERE category_code= '$category_code'
												AND commodity_code = '$commodity_code'
												AND org_sample_code = '$ogrsample_code'
												AND display = 'Y' ");
							}

						} elseif ($_SESSION['user_flag']=='CAL') {

							if ($result_flg=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET
														status_flag='FC',
														remark ='$remark',
														grade='$grade_code_vs',
														grading_date='$date',
														inward_grading_date='$date',
														sub_grad_check_iwo='$subGradeChecked',
														inward_grade='$grade_code_vs',
														grade_user_cd=".$_SESSION['user_code'].",
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														ral_anltc_rslt_rcpt_dt='$tran_date'
												WHERE category_code= '$category_code'
												AND commodity_code = '$commodity_code'
												AND org_sample_code = '$ogrsample_code'
												AND display = 'Y' ");

							} else {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET
														status_flag='VS',
														remark ='$remark',
														grade='$grade_code_vs',
														grading_date='$date',
														inward_grading_date='$date',
														sub_grad_check_iwo='$subGradeChecked',
														inward_grade='$grade_code_vs',
														grade_user_cd='".$_SESSION['user_code']."',
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														cal_anltc_rslt_rcpt_dt='$tran_date'
												WHERE category_code= '$category_code'
												AND commodity_code = '$commodity_code'
												AND org_sample_code = '$ogrsample_code'
												AND display = 'Y' ");
							}
						}

						if ($_SESSION['user_flag']=='RAL') {

							#SMS: Sample Finalized By Inward
							$this->DmiSmsEmailTemplates->sendMessage(109,$_SESSION["user_code"],$sample_code); 	#RAL
							$this->DmiSmsEmailTemplates->sendMessage(110,$dst_usr,$sample_code); 				#OIC
							$this->LimsUserActionLogs->saveActionLog('Sample Finalized Sent to RAL','Success'); #Action

							echo '#The results have been finalized and forwarded to RAL,Office Incharge#';
							exit;

						} elseif ($_SESSION['user_flag']=='CAL') {

							#SMS: Sample Finalized By Inward
							$this->DmiSmsEmailTemplates->sendMessage(111,$_SESSION["user_code"],$sample_code); 	#CAL
							$this->DmiSmsEmailTemplates->sendMessage(110,$dst_usr,$sample_code); 				#OIC
							$this->LimsUserActionLogs->saveActionLog('Sample Finalized Sent to CAL','Success'); #Action

							echo '#The results have been finalized and forwarded to CAL,Office Incharge#';
							exit;
						} else {
							echo '#Record Save Sucessfully!#';
							exit;
						}
					}
				}
			}
		}

	}


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>--------<Get Final Result>-------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/


	//Description : This is the new fuction is created from the above function with changes to show the no grades in ILC Module.
	//Author : Shreeya Bondre
	//Date : 15-07-2023
	public function ilcGetfinalResult(){
		$this->loadModel('CommGrade');
		$this->loadModel('MCommodity');
		$this->loadModel('FinalTestResult');
		$conn = ConnectionManager::get('default');

		$sample_code	= trim($_POST['sample_code']);
		$grd_standrd	= trim($_POST['grd_standrd']);
		$category_code	= trim($_POST['category_code']);
		$commodity_code	= trim($_POST['commodity_code']);

		if(!isset($sample_code) || !is_numeric($sample_code)){
			echo "#[error]~Invalid Sample code#";
			exit;
		}
		if(!isset($category_code) || !is_numeric($category_code)){
			echo "#[error]~Invalid Category code#";
			exit;
		}
		if(!isset($commodity_code) || !is_numeric($commodity_code)){
			echo "#[error]~Invalid Commodity code#";
			exit;
		}
		if(!isset($grd_standrd) || !is_numeric($grd_standrd)){
			echo "#[error]~Invalid Grading Standard#";
			exit;
		}

		$location_code	= $_SESSION['posted_ro_office'];
		$user_code		= $_SESSION['user_code'];
		$qry			= "SELECT t.test_code, t.test_name ,a.final_result
						   FROM final_test_result AS a
						   INNER JOIN m_test AS t ON t.test_code=a.test_code
						   WHERE a.display='Y' ";

		if ($_POST['sample_code']) {
			$qry.=	"and a.sample_code='$sample_code' ";
		}

		$res	= $conn->execute($qry);
		$res = $res->fetchAll('assoc');

		$i		= 0;
		$flag	= false;

		foreach ($res as $res1){

			$test = trim($res1['test_code']);
			$result = trim($res1['final_result']);

			$qry ="SELECT g.grade_desc ,t.grade_code,t.grade_value,t.max_grade_value,t.min_max,t.grade_order
					FROM comm_grade AS t
					INNER JOIN m_grade_desc AS g ON g.grade_code=t.grade_code
					WHERE t.test_code=$test AND  t.category_code=$category_code AND t.commodity_code=$commodity_code AND t.grd_standrd=$grd_standrd AND t.display='Y'
					ORDER BY t.grade_value";

			$grd_arr = $conn->execute($qry);
			$grd_arr = $grd_arr->fetchAll('assoc');

			if (!empty($grd_arr)) {

				foreach ($grd_arr as $grd_arr1){

					$grd_desc1			= '';
					$grade_value		= trim($grd_arr1['grade_value']);
					$max_grade_value	= trim($grd_arr1['max_grade_value']);
					$grade_desc			= trim($grd_arr1['grade_desc']);
					$min_max			= trim($grd_arr1['min_max']);
					$grade_order		= trim($grd_arr1['grade_order']);

					if (is_numeric($result)) {

						if ($min_max=='Max') {

							if ($grade_order==1) {

								if ($result<= $max_grade_value ) {

									$grd_desc1	= $grade_desc;
									break;

								} else {

									$grd_desc1	= 'Fail';
									break;
								}
							}
						}

						if ($min_max=='Min') {

							if($grade_order==1){

								if ($result>= $grade_value) {

										$grd_desc1	= $grade_desc;
										break;
								} else {
									$grd_desc1	= 'Fail';
									break;
								}
							}
						}

						if ($min_max=='Range' || $min_max=='') {

							if ($grade_order==1) {

								if ($result>=$grade_value && $result<=$max_grade_value) {

									$grd_desc1	= $grade_desc;
									break;
								} else {
									$grd_desc1	= 'Fail';
									break;
								}
							}
						}

					} else {

						if ($grade_order==1) {

							if (strcmp($grade_value,$result )==0) {

								$grd_desc1=$grade_desc;
								break;
							}else{
								$grd_desc1='Fail';
								break;
							}
						}
					}
				}

				if ($min_max=='Range') {

					$res[$i]['grd_val']	= $grade_value."-".$max_grade_value;

				} elseif ($min_max=='Min') {

					$res[$i]['grd_val']	= $grade_value." ".$min_max;

				} elseif ($min_max=='Max') {

					$res[$i]['grd_val']	= $max_grade_value." ".$min_max;

				} elseif ($min_max=='-1') {

					$res[$i]['grd_val']	= $grade_value;

				} else {

					$res[$i]['grd_val']	= "-";
					$res[$i]['grd_desc']	= $grd_desc1;
				}

				$res[$i]['grd_desc']	= $grd_desc1;
				$i++;

			} else {

				$flag=true;
			}
		}


		if ($flag==1) {
			echo "#~1#";
		} else {
			echo '#'.json_encode($res).'#';
		}

		exit;

	}

	//Description : This is the new fuction is created from the above function with changes to show the all grades to the OIC user.
	//Author : Akash Thakre
	//Date : 14-07-2023

	public function getfinalResult(){
		

		$sample_code = trim($_POST['sample_code']);
		$grd_standrd = trim($_POST['grd_standrd']);
		$category_code = trim($_POST['category_code']);
		$commodity_code = trim($_POST['commodity_code']);
		$conn = ConnectionManager::get('default');

		$test_string = array();
		$test_string_ext = array();

		$qry = "SELECT t.test_code, t.test_name, a.final_result
				FROM final_test_result AS a
				INNER JOIN m_test AS t ON t.test_code=a.test_code
				WHERE a.display='Y' ";

		if ($_POST['sample_code']) {
			$qry .= "and a.sample_code='$sample_code' ";
		}

		$res = $conn->execute($qry);
		$res = $res->fetchAll('assoc');
		
		$test_codes = array();

		foreach ($res as $each) {

			$test_code = $each['test_code'];
			
			if (!in_array($test_code, $test_codes)) {
				$test_string[] = array(
					'test_code' => $test_code,
					'test_name' => $each['test_name'],
					'final_results' => $each['final_result']
				);
				$test_codes[] = $test_code;
			} else {
				$index = array_search($test_code, $test_codes);
				$test_string[$index]['final_results'][] = $each['final_result'];
			}
		}

		$query = $conn->execute("SELECT DISTINCT(grade.grade_desc), grade.grade_code, test_code, max_grade_value
								FROM comm_grade AS cg
								INNER JOIN m_grade_desc AS grade ON grade.grade_code = cg.grade_code
								WHERE cg.commodity_code = '$commodity_code' AND cg.display = 'Y'");

		$commo_grade = $query->fetchAll('assoc');
		
		// Filter out repeated values based on grade_code
		$uniqueCommGrade = [];
		$gradeCodes = [];

		foreach ($commo_grade as $grade) {
			$gradeCode = $grade['grade_code'];
			if (!in_array($gradeCode, $gradeCodes)) {
				$uniqueCommGrade[] = $grade;
				$gradeCodes[] = $gradeCode;
			}
		}
		
		
		$this->loadModel('MGradeDesc');


		echo "<table class='table' border='1'>
			<thead class='tablehead'>
				<tr>
					<th>S.N</th>
					<th>Tests</th>
					<th>Readings</th>";

					// Generate dynamic table headings based on grade_desc values
					foreach ($gradeCodes as $val) {
						
						$label = $this->MGradeDesc->find()->select(['grade_desc'])->where(['grade_code IN' => $val])->first();
						echo "<th>" . $label['grade_desc'] . "</th>";
					}

		echo "</tr>
			</thead>
			<tbody>";
				
		$j = 1;

		foreach ($test_string as $row) {

			$test_code = $row['test_code'];
			$test_name = $row['test_name'];
			$final_results = $row['final_results'];


			echo "<tr>
			<td>" . $j . "</td>
			<td>" . $test_name . "</td>
			<td>" . $final_results . "</td>";

		
			foreach ($gradeCodes as $grade_code) {
				$query = $conn->execute("SELECT max_grade_value,grade_value
										FROM comm_grade
										WHERE commodity_code = '$commodity_code' AND grade_code = '$grade_code' AND test_code = '$test_code'");

				$result = $query->fetch('assoc');
			
				$max_grade_value = $result ? $result['max_grade_value'] : '';
				echo "<td>" . ($max_grade_value !== null ? $max_grade_value : ($result['grade_value'] !== null ? $result['grade_value'] : 'N/A')) . "</td>";
				
			
			}

			echo "</tr>";

			$j++;
		}

		echo "</tbody>
		</table>";

		exit;
	}
	

/***************************************************************************************************************************************************************************************/


	// get grade list commodity wise,
	public function getSampleCommodityGrads(){

		$this->autoRender = false;
		$this->loadModel('CommGrade');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');

		$commodity_code = $_POST['commodity_code'];
		$sample_code = $_POST['sample_code'];
		$conn = ConnectionManager::get('default');

		//Fetch new field "sub_grad_check_iwo" data
		$query = $conn->execute("SELECT si.grade,si.sub_grad_check_iwo
								 FROM workflow AS w
								 INNER JOIN sample_inward AS si ON si.org_sample_code = w.org_sample_code
								 WHERE w.stage_smpl_cd='$sample_code'");

		$inward_grade = $query->fetchAll('assoc');


		$query = $conn->execute("SELECT gd.grade_desc,gd.grade_code
								 FROM comm_grade AS cg
								 INNER JOIN m_grade_desc AS gd ON gd.grade_code = cg.grade_code
								 WHERE cg.commodity_code='$commodity_code' AND cg.display='Y'");

		$commodity_code_grades = $query->fetchAll('assoc');

		$unique_result = array_unique($commodity_code_grades, SORT_REGULAR);

		$finalresult[0] = $inward_grade;
		$i=1;

		foreach($unique_result as $each){

			$finalresult[$i]['grade_code'] = $each['grade_code'];
			$finalresult[$i]['grade_desc'] = $each['grade_desc'];
			$i++;
		}

		// Add new option in grading drop down,
		$finalresult[$i] = Array ( 'grade_desc' => 'Fail' ,'grade_code' => 348 );

		echo '#'.json_encode($finalresult).'#';
		exit;
	}

/***************************************************************************************************************************************************************************************/

	//added for redirection for normal & ilc flow
	public function availableForGradingToOic(){

		$this->authenticateUser();

		$result = $this->getSampleToGradeByOic();
		$this->set('sample_codes',$result[0]);	//add array for showing result[0] regular flow 08-07-2022
		$this->set('sample_codes1',$result[1]);	//add array for showing result[1] ilc flow 08-07-2022

	}

/***************************************************************************************************************************************************************************************/


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>----------<Get Sample to Grade By OIC>---------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

	//created common function to fetch list , to be used for dashboard counts also, on 28-04-2021 by Amol
	public function getSampleToGradeByOic(){

		$conn = ConnectionManager::get('default');
		$user_id = $_SESSION['user_code'];
		$this->loadModel('Workflow');
		$this->loadComponent('Ilc');

		if ($_SESSION['role']=='RAL/CAL OIC') {


			/* Add 'VS' flag options in stage_smpl_flag and status_flag conditions,
			Add 'FGIO' flag options in stage_smpl_flag and status_flag conditions //
			Why : To show the finalized test sample to inward officer if sample forward by Lab incharge officer, */

			$query = $conn->execute("SELECT ft.sample_code,ft.sample_code
										FROM Final_Test_Result AS ft
										INNER JOIN workflow AS w ON ft.org_sample_code=w.org_sample_code
										INNER JOIN m_sample_allocate sa ON ft.org_sample_code=sa.org_sample_code
										INNER JOIN sample_inward AS si ON ft.org_sample_code=si.org_sample_code
										WHERE si.status_flag !='junked' AND w.stage_smpl_cd NOT IN ('','blank') AND si.sample_type_code != 9 AND ft.display='Y' AND w.dst_usr_cd='$user_id' AND w.stage_smpl_flag IN ('AR','FO','FC','FG','FS','VS','FGIO') AND  si.status_flag IN('VS','FG','FC','FO','FS')
										GROUP BY ft.sample_code ");
										//Conditions to check wheather sample type ilc is not present
			$final_result_details = $query->fetchAll('assoc');

			//Conditions to check wheather stage sample code is final graded or not.
			$final_result = array();
			if(!empty($final_result_details)){

				foreach($final_result_details as $stage_sample_code){

					$final_grading = $this->Workflow->find('all',array('conditions'=>array('stage_smpl_flag'=>'FG','stage_smpl_cd'=>$stage_sample_code['sample_code'],'src_usr_cd'=>$user_id)))->first();

					if(empty($final_grading)){
						$final_result[]= $stage_sample_code;
					}
				}
			}


			//Conditions to check wheather is not sample type ilc - Shreeya [11-07-2022]
			$final_result1 = $this->Ilc->ilcFinalGradeAvaiIf($user_id);

		} else {

			/* Add 'FR' flag options in stage_smpl_flag and status_flag conditions and destination user id conditions
			Why : To show the finalized test sample to OIC or Lab inward officer if sample forward by inward officer, */

			$query = $conn->execute("SELECT ft.sample_code,ft.sample_code
									 FROM Final_Test_Result AS ft
									 INNER JOIN workflow AS w ON ft.org_sample_code=w.org_sample_code
									 INNER JOIN m_sample_allocate sa ON ft.org_sample_code=sa.org_sample_code
									 INNER JOIN sample_inward AS si ON ft.org_sample_code=si.org_sample_code
									 WHERE ft.display='Y' AND w.stage_smpl_cd NOT IN ('','blank') 
									 AND w.dst_usr_cd='$user_id'
									 AND w.stage_smpl_flag IN('AR','FO','FC','FR')
									 AND  si.status_flag IN('VS','FO','FC','FR')
									 GROUP BY ft.sample_code");
									// stage_smpl_cd not in '', blank added condi. by shankhpal on 21-04-2023

			$final_result_details = $query->fetchAll('assoc');

			/* Conditions to check wheather stage sample code is final graded or not.*/
			$final_result = array();
			if (!empty($final_result_details)) {

				foreach ($final_result_details as $stage_sample_code) {

					$final_grading_details = $this->Workflow->find('all',array('conditions'=>array('stage_smpl_cd'=>$stage_sample_code['sample_code']),'order'=>array('id desc')))->first();

					if (!empty($final_grading_details)) {

						$final_grading = $this->Workflow->find('all',array('conditions'=>array('dst_usr_cd'=>$user_id,'id'=>$final_grading_details['id'],'stage_smpl_flag !='=>'FG')))->first();

						if (!empty($final_grading)) {
							$final_result[]= $stage_sample_code;
						}
					}
				}
			}

			//Conditions to check wheather is not sample type ilc - Shreeya [11-07-2022]
			$final_result1 = $this->Ilc->ilcFinalGradeAvaiElse($user_id);

		}

		//to be used in below core query format, that's why
		$arr = "IN(";
		foreach ($final_result as $each) {
			$arr .= "'";
			$arr .= $each['sample_code'];
			$arr .= "',";
		}
		$arr .= "'00')";//00 is intensionally given to put last value in string.

		//update the query to avoid duplicate entry in result, done by pravin bhakare 29-10-2021
		// NOTE : ADDED THE "VS" FLAG IN THIS QUERY TO GET THE VERFIED SAMPLE LIST AVALIBLE FOR GRADING AT THE OIC - 26-05-2022
		$query = $conn->execute("SELECT workflows.stage_smpl_cd,
		                si.received_date,
		                st.sample_type_desc,
		                mcc.category_name,
		                mc.commodity_name,
		                ml.ro_office,
		                workflows.modified AS submitted_on
		            FROM sample_inward AS si
		            INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
		            INNER JOIN m_commodity_category AS mcc ON si.category_code=mcc.category_code
		            INNER JOIN dmi_ro_offices AS ml ON ml.id=si.loc_id
		            INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
		            INNER JOIN (select org_sample_code,stage_smpl_cd,modified from workflow where stage_smpl_flag IN('FGIO','FS','FC','VS') GROUP by org_sample_code,stage_smpl_cd,modified) as workflows
		                      on si.org_sample_code = workflows.org_sample_code
		            WHERE workflows.stage_smpl_cd ".$arr." ORDER BY workflows.modified desc "  );

		$result = $query->fetchAll('assoc');
		// added for ilc flow 11-07-2022
		$result1 = $this->Ilc->finalgradingresult($final_result1);

		$newResult = array($result,$result1);
		return $newResult;

	}

/*******************************************************************************************************************************************************************************************************/

	public function redirectToGrade($grading_sample_code){

		$this->Session->write('grading_sample_code',$grading_sample_code);
		$this->redirect(array('controller'=>'FinalGrading','action'=>'grading_by_oic'));
	}

/******************************************************************************************************************************************************************************************************/


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>----------<GRADING BY OIC>---------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

	public function gradingByOic(){

		$this->authenticateUser();
		$this->viewBuilder()->setLayout('admin_dashboard');
		$str1		  = "";
		$this->loadModel('MCommodityCategory');
		$this->loadModel('DmiUsers');
		$this->loadModel('FinalTestResult');
		$this->loadModel('MGradeStandard');
		$this->loadModel('MTestMethod');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('MSampleAllocate');
		$this->loadModel('MCommodity');
		$this->loadModel('MGradeDesc');
		$this->loadModel('MSampleType');
		$this->loadModel('DmiRoOffices');
		$conn = ConnectionManager::get('default');

		$grading_sample_code = $this->Session->read('grading_sample_code');

		if(!empty($grading_sample_code)){

			$this->set('samples_list',array($grading_sample_code=>$grading_sample_code));
			$this->set('stage_sample_code',$grading_sample_code);//for hidden field, to use common script

			$grades_strd=$this->MGradeStandard->find('list',array('keyField'=>'grd_standrd','valueField'=>'grade_strd_desc','order' => array('grade_strd_desc' => 'ASC')))->toArray();
			$this->set('grades_strd',$grades_strd);

			$grades=$this->MGradeDesc->find('list',array('keyField'=>'grade_code','valueField'=>'grade_desc','order' => array('grade_desc' => 'ASC'),'conditions' => array('display' => 'Y')))->toArray();
			$this->set('grades',$grades);

			//get org samle code
			$ogrsample1= $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $grading_sample_code)))->first();
			$ogrsample = $ogrsample1['org_sample_code'];

			//to get commodity code for report pdf
			$getcommoditycd = $this->SampleInward->find('all',array('fields'=>'commodity_code','conditions'=>array('org_sample_code IS'=>$ogrsample),'order'=>'inward_id desc'))->first();
			$smple_commdity_code = $getcommoditycd['commodity_code'];
			$this->set('smple_commdity_code',$smple_commdity_code);

			
			if ($this->request->is('post')) {

				//html encode the each post inputs
				$postdata = $this->request->getData();

				foreach ($postdata as $key => $value) {

					$data[$key] = htmlentities($this->request->getData($key), ENT_QUOTES);
				}

				$sample_code = $this->request->getData('sample_code');

				if ($this->request->getData('button')=='add') {

					// Add new filed to add subgrading value
					$subGradeChecked = $this->request->getData('subgrade');

					$category_code=$this->request->getData('category_code');

					$commodity_code=$this->request->getData('commodity_code');

					$remark=$this->request->getData('remark');

					$remark_new=$this->request->getData('remark_new');


					if (null!==($this->request->getData('result_flg'))) {

						$result_flg	= $this->request->getData('result_flg');

					} else {

						$result_flg="";
					}

					$result_grade	=	'';
					$grade_code_vs=$this->request->getData('grade_code');

					$tran_date=$this->request->getData("tran_date");

					if ($result_flg=='R') {

						$src_usr_cd = $conn->execute("SELECT src_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='TA' ");
						$src_usr_cd = $src_usr_cd->fetchAll('assoc');
						$abc = $src_usr_cd[0]['src_usr_cd'];

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] = $_SESSION["user_code"];

						$workflow_data = array("org_sample_code"=>$ogrsample,
												"src_loc_id"=>$_SESSION["posted_ro_office"],
												"src_usr_cd"=>$_SESSION["user_code"],
												"dst_loc_id"=>$_SESSION["posted_ro_office"],
												"dst_usr_cd"=>$abc,
												"stage_smpl_flag"=>"R",
												"tran_date"=>$tran_date,
												"user_code"=>$_SESSION["user_code"],
												"stage_smpl_cd"=>$sample_code,
												"stage"=>"8");

						$workflowEntity = $this->Workflow->newEntity($workflow_data);
						$this->Workflow->save($workflowEntity);

						$dst_usr_cd = $conn->execute("SELECT dst_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' and stage_smpl_flag='R' ");

						$dst_usr_cd = $dst_usr_cd->fetchAll('assoc');
						$abcd = $dst_usr_cd[0]['dst_usr_cd'];

						$user_name = $conn->execute("SELECT DISTINCT role
													 FROM dmi_users AS u
													 INNER JOIN workflow AS w ON u.id=w.dst_usr_cd
											         INNER JOIN user_role AS r ON u.role=r.role_name
											         WHERE dst_usr_cd='$abcd' AND org_sample_code='$ogrsample' AND stage_smpl_flag='R' ");

						$user_name = $user_name->fetchAll('assoc');

						$abc2 = $user_name[0]['role'];

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] = $_SESSION["user_code"];
						$date=date("Y/m/d");
						$sample_code = trim($this->request->getData('sample_code'));

						$query = $conn->execute("SELECT si.org_sample_code
												 FROM sample_inward AS si
												 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												 WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($result_flg=='F') {

							$result_flg='Fail';
						} elseif ($result_flg=='M') {

							$result_flg='Misgrade';

						} else {
							$result_flg='Retest';
						}

						 // Add two new fileds to add subgrading value and oic grading date ,
						$conn->execute("UPDATE  sample_inward SET
											    remark ='$result_flg',
												remark_officeincharg ='$remark_new',
												status_flag='SR',grade='$grade_code_vs',
												grading_date='$date',
												oic_grading_date='$date',
												sub_grad_check_oic='$subGradeChecked'
										WHERE category_code= '$category_code'
										AND commodity_code = '$commodity_code'
										AND org_sample_code = '$ogrsample_code'
										AND display = 'Y' ");

						#SMS: Sample Marked For Retest By OIC
						$this->DmiSmsEmailTemplates->sendMessage(118,$_SESSION["user_code"],$sample_code); 	#OIC
						$this->DmiSmsEmailTemplates->sendMessage(119,$abc,$sample_code); 					#INWARD
						$this->LimsUserActionLogs->saveActionLog('Sample Sent For Retest','Success'); 		#Action

						echo '#0#';  // return 0 value to show conformation message
						exit;

					} else {

						//code moved to below new function save grading
					}
				}
			}
		}

	}

/******************************************************************************************************************************************************************************************************/

	//to set post values in session to be used after redirecting from cdac
	public function setPostSessions(){

		$this->Session->write('post_remark',$_POST['remark']);//inward officer renark
		$this->Session->write('post_remark_new',$_POST['remark_new']);//Incharge remark
		$this->Session->write('post_grade_code_vs',$_POST['grade_code']);
		$grading_sample_code = $this->Session->read('grading_sample_code');

		//get grade desc from table, added on 27-05-2022 by Amol
		//to show grade selected by OIC while final grading on report pdf
		$this->loadModel('MGradeDesc');

		// call to component for show sample type Done by Shreeya on 18-11-2022
		$sampleTypeCode = $this->Customfunctions->createSampleType($grading_sample_code);

		// added if conditon for ILC sample non grading done by Shreeya on 18-11-2022
		if($sampleTypeCode!=9){

			$getGradedesc = $this->MGradeDesc->find('all',array('fields'=>'grade_desc','conditions'=>array('grade_code'=>$_POST['grade_code'])))->first();
			$this->Session->write('gradeDescFinalReport',$getGradedesc['grade_desc']);
			$this->Session->write('post_subGradeChecked',$_POST['subgrade']);

		}

		$this->Session->write('post_category_code',$_POST['category_code']);
		$this->Session->write('post_commodity_code',$_POST['commodity_code']);

		echo '#1#';
		exit;
	}

/******************************************************************************************************************************************************************************************************/


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>??>>>>>>>--------<Save Final Grading>-------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

	//called after successful esigned by OIC
	public function saveFinalGrading(){

		// set variables to show popup messages from view file
		$message = '';
		$message_theme = '';
		$redirect_to = '';

		$this->loadModel('Workflow');
		$this->loadModel('DmiRoOffices');
		$this->loadModel('DmiUsers');
		$this->loadModel('MCommodity');
		$this->loadModel('MSampleType');
		$this->loadModel('SampleInward');

		$conn = ConnectionManager::get('default');

		$sample_code = $this->Session->read('grading_sample_code');

		//get org sample code
		$ogrsample1 = $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $sample_code)))->first();
		$ogrsample = $ogrsample1['org_sample_code'];

		$src_usr_cd = $conn->execute("SELECT src_usr_cd,src_loc_id FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag IN ('OF','IF') ");

		$src_usr_cd = $src_usr_cd->fetchAll('assoc');
		$org_src_usr_cd = $src_usr_cd[0]['src_usr_cd'];
		$org_src_usr_id = $src_usr_cd[0]['src_loc_id'];

		$tran_date = date('Y-m-d');

		$workflow_data = array("org_sample_code"=>$ogrsample,
								"src_loc_id"=>$_SESSION["posted_ro_office"],
								"src_usr_cd"=>$_SESSION["user_code"],
								"dst_loc_id"=>$org_src_usr_id,
								"dst_usr_cd"=>$org_src_usr_cd,
								"stage_smpl_flag"=>"FG",
								"tran_date"=>$tran_date,
								"user_code"=>$_SESSION["user_code"],
								"stage_smpl_cd"=>$sample_code,
								"stage"=>"8");


		$workflowEntity = $this->Workflow->newEntity($workflow_data);

		$this->Workflow->save($workflowEntity);

		$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];
		$_SESSION["loc_user_id"] = $_SESSION["user_code"];
		$date = date("Y/m/d");

		//get some post value from session after redirecting form cdac
		$remark = $this->Session->read('post_remark');//inward officer renark
		$remark_new = $this->Session->read('post_remark_new');//Incharge remark
		$grade_code_vs = $this->Session->read('post_grade_code_vs');
		$subGradeChecked = $this->Session->read('post_subGradeChecked');
		$category_code = $this->Session->read('post_category_code');
		$commodity_code = $this->Session->read('post_commodity_code');

		//applied sample type condition for ILC sample to bypass grade variables
		//29-11-2022 by Amol
		$sampleTypeCode = $this->Customfunctions->createSampleType($sample_code);
		if($sampleTypeCode==9){
			$grade_code_vs = 0;
			$subGradeChecked = '';
		}

		if ($_SESSION['user_flag']=='RAL' && $_SESSION['role']=='RAL/CAL OIC') {

			// Add two new fileds to add subgrading value and oic grading date ,
			$conn->execute("UPDATE sample_inward SET
									status_flag='FG',
									remark ='$remark',
									grade='$grade_code_vs',
									remark_officeincharg ='$remark_new',
									remark_officeincharg_dt='$tran_date',
									grading_date='$date',
									oic_grading_date='$date',
									sub_grad_check_oic='$subGradeChecked',
									grade_user_cd=".$_SESSION['user_code'].",
									grade_user_flag='".$_SESSION['user_flag']."',
									grade_user_loc_id='".$_SESSION['posted_ro_office']."',
									ral_anltc_rslt_rcpt_dt='$tran_date'
								WHERE category_code= '$category_code'
								AND commodity_code = '$commodity_code'
								AND org_sample_code = '$ogrsample'
								AND display = 'Y' ");

		} elseif ($_SESSION['user_flag']=='CAL' && $_SESSION['role']=='DOL' || $_SESSION['role']=='RAL/CAL OIC') {

			// Add two new fileds to add subgrading value and oic grading date ,
			$conn->execute("UPDATE sample_inward SET
									status_flag='G',
									remark ='$remark',
									grade='$grade_code_vs',
									remark_officeincharg ='$remark_new',
									remark_officeincharg_dt='$tran_date',
									grading_date='$date',
									oic_grading_date='$date',
									sub_grad_check_oic='$subGradeChecked',
									grade_user_cd='".$_SESSION['user_code']."',
									grade_user_flag='".$_SESSION['user_flag']."',
									grade_user_loc_id='".$_SESSION['posted_ro_office']."',
									cal_anltc_rslt_rcpt_dt='$tran_date'
							WHERE category_code= '$category_code'
							AND commodity_code = '$commodity_code'
							AND org_sample_code = '$ogrsample'
							AND display = 'Y' ");
		}

		//delete all used session to clear memory
		$this->Session->delete('grading_sample_code');
		$this->Session->delete('post_remark');
		$this->Session->delete('post_remark_new');
		$this->Session->delete('post_grade_code_vs');
		$this->Session->delete('post_subGradeChecked');
		$this->Session->delete('post_category_code');
		$this->Session->delete('post_commodity_code');

		$userRole = $this->DmiUsers->getUserDetailsById($org_src_usr_cd);
		
		//This all code block is added to send the message after the esign to ROSOOIC - Akash [16-03-2023]
		//to get commodity code for report pdf
		$sampleDet = $this->SampleInward->find('all',array('conditions'=>array('org_sample_code IS'=>$ogrsample),'order'=>'inward_id desc'))->first();
		#this is to get the RO/SO OIC Office Id incharge email from the Original Sample location id of source.
		$getRsoId = $this->DmiRoOffices->getRoOfficeEmail($ogrsample1['src_loc_id']); 
		#this is to get the table id of the ro so oic incharge by email
		$originalUser = $this->DmiUsers->find()->where(['email IS' => $getRsoId,'status !=' => 'disactive'])->first();
		#this is to get the RAL/CAL OIC Office Id incharge email from the Original Sample location id of source.
		$getRcoId = $this->DmiRoOffices->find()->where(['id IS'=>$_SESSION['posted_ro_office']])->first();
		$ralCalOicId = $this->DmiUsers->getUserTableId($getRcoId['ro_email_id']);
		
		//SMS Parameters FOR RO/SO OIC
		$commodityused = $this->MCommodity->getCommodity($sampleDet['commodity_code']);
		$sample_flow = $this->MSampleType->getSampleType($sampleDet['sample_type_code']);
		$sms_text = 'Hello, '.$originalUser['f_name']." ".$originalUser['l_name'].', The sample for '.$sample_flow.' process, having SAMPLE CODE : '.$ogrsample.' for COMMODITY : '.$commodityused.' is final graded by '.$_SESSION['role'].' - '.$_SESSION['ro_office'].' and the final report is available. AGMARK';
		$mobile_no = $originalUser['phone'];
		$emailForRo = $originalUser['email'];
		
		//$this->Authentication->sendSms(115,$mobile_no,$sms_text,1107166478794070620); #RO/SO OIC - SMS
		//$this->Authentication->sendEmail($emailForRo,$sms_text,115,1107166478794070620,'Final Grading'); #RO/SO OIC - EMAIL
		//$this->DmiSmsEmailTemplates->sendMessage(116,$ralCalOicId,$sample_code); #RAL CAL OIC

		#Action
		$this->LimsUserActionLogs->saveActionLog('Sample Finalized','Success');

		$message = 'Records has been Finalized and Sent to respective	'.	$userRole['role']; #this variable is added to display the name who filed sample. - Akash [02-03-2023]
		$message_theme = 'success';
		$redirect_to = 'available_for_grading_to_oic';

		// set variables to show popup messages from view file
		$this->set('message',$message);
		$this->set('message_theme',$message_theme);
		$this->set('redirect_to',$redirect_to);

	}

/******************************************************************************************************************************************************************************************************/


	public function getRemark(){

		$this->loadModel('Users');
		$this->loadModel('DmiUsers');
		$conn = ConnectionManager::get('default');

		if($_POST['sample_code'])
		{
		 $sample_code=trim($_POST['sample_code']);
		}

		$user_data = $conn->execute("SELECT DISTINCT remark
									 FROM sample_inward AS si
									 INNER JOIN workflow AS w ON w.org_sample_code=si.org_sample_code
									 WHERE w.stage_smpl_cd='$sample_code' ");

		$user_data = $user_data->fetchAll('assoc');

		if (count($user_data)>0) {
			echo '#'.json_encode($user_data).'#';

		} else {

		   echo "#0#";
		}
		exit;
	}

/******************************************************************************************************************************************************************************************************/


	public function finalizedSamples(){

		$final_reports = $this->finalSampleTestReports();
		$this->set('final_sample_reports',$final_reports);
	}

/******************************************************************************************************************************************************************************************************/


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>---------------<Final Sample Test Reports>--------------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

	// create new menu for showing finalized test report result,
	public function finalSampleTestReports(){

		$this->viewBuilder()->setLayout('admin_dashboard');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$conn = ConnectionManager::get('default');

		$result = $this->Workflow->find('all',array('fields'=>array('modified','org_sample_code'),'conditions'=>array('src_usr_cd IS'=>$_SESSION['user_code']),'group'=>array('modified','org_sample_code'),'order'=>'modified desc'))->toArray();

		$final_reports = array();

		if (!empty($result)) {

			foreach ($result as $sample_code) {

				$org_smpl_cd = $sample_code['org_sample_code'];

				#=> Query Updated :: /* New status Flag is added i,e status_flah != 'junked' - Shankhpal*/
				$query = $conn->execute("SELECT w.stage_smpl_cd, w.tran_date,mcc.category_name, mc.commodity_name, mst.sample_type_desc, mc.commodity_code, si.report_pdf
										 FROM workflow AS w
										 INNER JOIN sample_inward AS si ON si.org_sample_code = w.org_sample_code
										 INNER JOIN m_commodity_category AS mcc ON mcc.category_code = si.category_code
										 INNER JOIN m_commodity AS mc ON mc.commodity_code = si.commodity_code
										 INNER JOIN m_sample_type AS mst ON mst.sample_type_code = si.sample_type_code
										 WHERE si.status_flag != 'junked' AND w.stage_smpl_flag='FG' AND w.org_sample_code='$org_smpl_cd'");
										// si.status_flag != 'junked' added by shankhpal shende on 10/03/2023

				$final_grading = $query->fetchAll('assoc');


				if (!empty($final_grading)) {

					$final_reports[] = $final_grading[0];
				}
			}
		}

		$this->set('final_sample_reports',$final_reports);

		return $final_reports;
	}

/******************************************************************************************************************************************************************************************************/

	//to generate report pdf for preview and store on server
	public function sampleTestReportCode($sample_code,$sample_test_mc){

		$conn = ConnectionManager::get('default');

		$this->Session->write('sample_test_code',$sample_code);
		$this->Session->write('sample_test_mc',$sample_test_mc);

		// call to component for sample type Done by Shreeya on 15-11-2022
		$sampleTypeCode = $this->Customfunctions->createSampleType($sample_code);

		// added if conditon for ILC sample non grading done by Shreeya on 15-11-2022
		if($sampleTypeCode!=9){

			// // Added by AKASH on 10-08-2022
			$sd = $conn->execute("SELECT org_sample_code FROM workflow WHERE stage_smpl_cd = '$sample_code'")->fetch('assoc');
			$code2 = $sd['org_sample_code'];

			$grade = $conn->execute("SELECT gd.grade_desc
									FROM sample_inward AS si
									INNER JOIN m_grade_desc AS gd ON gd.grade_code = si.grade
									WHERE si.org_sample_code = '$code2'")->fetchAll('assoc');

			$this->Session->write('gradeDescFinalReport',$grade[0]['grade_desc']);
		}

		$this->redirect(array('controller'=>'FinalGrading','action'=>'sample_test_report'));
	}

/******************************************************************************************************************************************************************************************************/


/*>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>---------------<Sample Test Reports>--------------->>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>*/

	public function sampleTestReport(){

		$this->viewBuilder()->setLayout('pdf_layout');

		$this->loadModel('SampleInward');
		$this->loadModel('FinalTestResult');
		$this->loadModel('ActualTestData');
		$this->loadModel('CommGrade');
		$this->loadModel('MSampleAllocate');
		$this->loadModel('Workflow');
		$this->loadModel('CommGrade');
		$conn = ConnectionManager::get('default');

		$commodity_code=$this->Session->read('sample_test_mc');
		$sample_code1=$this->Session->read('sample_test_code');

		$str1="SELECT org_sample_code FROM workflow WHERE display='Y' ";

		if ($sample_code1!='') {

			$str1.=" AND stage_smpl_cd='$sample_code1' GROUP BY org_sample_code"; /* remove trim fun on 01/05/2022 */
		}

		$sample_code2 = $conn->execute($str1);
		$sample_code2 = $sample_code2->fetchAll('assoc');

		$Sample_code = $sample_code2[0]['org_sample_code'];

		$str2="SELECT stage_smpl_cd FROM workflow WHERE display='Y' ";

		if ($sample_code1!='') {

			$str2.=" AND org_sample_code='$Sample_code' AND stage_smpl_flag='AS' GROUP BY stage_smpl_cd";
		}

		//add component for get sample type Done By Shreeya on 18-11-2022
		$sampleTypeCode = $this->Customfunctions->createSampleType($Sample_code);
		$this->set('sampleTypeCode',$sampleTypeCode);

		$checkifmainilc = array();
		if($sampleTypeCode == 9){
			// to check if this is a main orignal sample came for final report	 Done By Shreeya on 18-11-2022
			$checkifmainilc = $this->SampleInward->find('all',array('fields'=>'org_sample_code','conditions'=>array('org_sample_code'=>$Sample_code,'entry_type IS NULL')))->first();

			if(!empty($checkifmainilc)){
				$str2="SELECT stage_smpl_cd FROM workflow WHERE display='Y' AND org_sample_code='$Sample_code' AND stage_smpl_flag='FGIO' GROUP BY stage_smpl_cd";
				$str="";
				$test_report="";

				$this->ilcAvailableSampleZscore();
			}

		}

		$this->set('checkifmainilc',$checkifmainilc);


		$sample_code3 = $conn->execute($str2);
		$sample_code3 = $sample_code3->fetchAll('assoc');

		$Sample_code_as=trim($sample_code3[0]['stage_smpl_cd']);

		$this->set('Sample_code_as',$Sample_code_as);

		$this->loadModel('MSampleRegObs');

		$query2 = "SELECT msr.m_sample_reg_obs_code, mso.m_sample_obs_code, mso.m_sample_obs_desc, mst.m_sample_obs_type_code,mst.m_sample_obs_type_value
				   FROM m_sample_reg_obs AS msr
				   INNER JOIN m_sample_obs_type AS mst ON mst.m_sample_obs_type_code=msr.m_sample_obs_type_code
				   INNER JOIN m_sample_obs AS mso ON mso.m_sample_obs_code=mst.m_sample_obs_code AND stage_sample_code='$Sample_code_as'
				   GROUP BY msr.m_sample_reg_obs_code,mso.m_sample_obs_code,mso.m_sample_obs_desc,mst.m_sample_obs_type_code,mst.m_sample_obs_type_value";

		$method_homo = $conn->execute($query2);
		$method_homo = $method_homo->fetchAll('assoc');

		$this->set('method_homo',$method_homo);

		if (null!==($this->request->getData('ral_lab'))) {

			$data=$this->request->getData('ral_lab');

			$data1=explode("~",$data);

			if ($data1[0]!='all') {

				$ral_lab=$data1[0];
				$ral_lab_name=$data1[1];
				$this->set('ral_lab_name',$ral_lab_name);

			} else {

				$ral_lab=$data1[0];
				$ral_lab_name='all';
			}

		} else {

			$ral_lab='';
			$ral_lab_name='all';
		}




		$test = $this->ActualTestData->find('all', array('fields' => array('test_code'=>'distinct(test_code)'),'conditions' =>array('org_sample_code IS' => $Sample_code, 'display' => 'Y')))->toArray();

		$test_string=array();
		$test_string_ext=array();

		$i=0;

		foreach ($test as $each) {

			$test_string[$i]=$each['test_code'];
			$i++;
		}

		//new queries and conditions added on 03-02-2022 by Amol
		//to print NABL logo and ULR no. on final test report

		$showNablLogo = ''; $urlNo=''; $certNo='';
		//get NABL commosity and test details if exist
		$this->loadModel('LimsLabNablCommTestDetails');
		$NablTests = $this->LimsLabNablCommTestDetails->find('all',array('fields'=>'tests','conditions'=>array('lab_id IS'=>$_SESSION['posted_ro_office'],'commodity IS'=>$commodity_code),'order'=>'id desc'))->first();

		if(!empty($NablTests)){
			//get NABL certifcate details
			$this->loadModel('LimsLabNablDetails');
			$NablDetails = $this->LimsLabNablDetails->find('all',array('fields'=>array('accreditation_cert_no','valid_upto_date'), 'conditions'=>array('lab_id IS'=>$_SESSION['posted_ro_office']),'order'=>'id desc'))->first();
			//check validity //added str_replace on 14-09-2022 by Amol
			$validUpto = strtotime(str_replace('/','-',$NablDetails['valid_upto_date']));
			$curDate = strtotime(date('d-m-Y'));

			if($validUpto > $curDate){

				$showNablLogo = 'yes';
				$certNo = $NablDetails['accreditation_cert_no'];
				$curYear = date('y');
				//Custom array for Lab no.
				$labNoArr = array('55'=>'0','56'=>'1','45'=>'2','46'=>'3','47'=>'4','48'=>'5','49'=>'6','50'=>'7','51'=>'8','52'=>'9','53'=>'10','54'=>'11');
				$labNo = $labNoArr[$_SESSION['posted_ro_office']];

				//get total report for respective lab for current year
				$newDate = '01-01-'.date('Y');
				$getReportsCounts = $this->Workflow->find('all',array('fields'=>'id','conditions'=>array('src_loc_id'=>$_SESSION['posted_ro_office'],'stage_smpl_flag'=>'FG','date(tran_date) >=' =>$newDate,)))->toArray();
				$NoOfReport = '';
				for($i=0;$i<(8-(strlen(count($getReportsCounts))));$i++){
					$NoOfReport .= '0';
				}
				if(count($getReportsCounts)==0){
					$NoOfReport .= '1';
				}else{
					$NoOfReport .= count($getReportsCounts)+1;
				}


				$NablTests = explode(',',$NablTests['tests']);
				//compare tests arrays
				$result=array_diff($test_string,$NablTests);
				if(!empty($result)){$F_or_P = 'P';}else{$F_or_P = 'F';}

				$urlNo = 'ULR-'.$certNo.$curYear.$labNo.$NoOfReport.$F_or_P;

				//to get tests with accreditation
				$accreditatedtest = $this->ActualTestData->find('all', array('fields' => array('test_code'=>'distinct(test_code)'),'conditions' =>array('org_sample_code IS' => $Sample_code, 'test_code IN'=>$NablTests, 'display' => 'Y')))->toArray();
				$test_string=array();
				$i=0;
				foreach ($accreditatedtest as $each) {

					$test_string[$i]=$each['test_code'];
					$i++;
				}

				//to get tests without accreditation
				$nonAccreditatedtest = $this->ActualTestData->find('all', array('fields' => array('test_code'=>'distinct(test_code)'),'conditions' =>array('org_sample_code IS' => $Sample_code, 'test_code NOT IN'=>$NablTests, 'display' => 'Y')))->toArray();
				$i=0;
				foreach ($nonAccreditatedtest as $each) {

					$test_string_ext[$i]=$each['test_code'];
					$i++;
				}
			}
		}

		$this->set(compact('showNablLogo','urlNo','certNo'));

		foreach($test_string as $row1) {

			$query = $conn->execute("SELECT DISTINCT(grade.grade_desc),grade.grade_code,test_code
										FROM comm_grade AS cg
										INNER JOIN m_grade_desc AS grade ON grade.grade_code = cg.grade_code
										WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row1' AND cg.display = 'Y'");

			$commo_grade = $query->fetchAll('assoc');
			$str="";

			$this->set('commo_grade',$commo_grade );
		}

		$j=1;

		foreach ($test_string as $row) {

			$query = $conn->execute("SELECT cg.grade_code,cg.grade_value,cg.max_grade_value,cg.min_max
									 FROM comm_grade AS cg
									 INNER JOIN m_test_method AS tm ON tm.method_code = cg.method_code
									 INNER JOIN m_test AS t ON t.test_code = cg.test_code
									 WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row' AND cg.display = 'Y'
									 ORDER BY cg.grade_code ASC");


			$data = $query->fetchAll('assoc');


			$query = $conn->execute("SELECT t.test_name,tm.method_name
										FROM comm_grade AS cg
										INNER JOIN m_test_method AS tm ON tm.method_code = cg.method_code
										INNER JOIN m_test AS t ON t.test_code = cg.test_code
										INNER JOIN test_formula AS tf ON tf.test_code = cg.test_code AND tm.method_code = cg.method_code
										WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row' AND cg.display = 'Y'
										ORDER BY t.test_name ASC");

			$data1 = $query->fetchAll('assoc');

			if (!empty($data1)) {

				$data_method_name = $data1[0]['method_name'];
				$data_test_name = $data1[0]['test_name'];

			} else {

				$data_method_name = '';
				$data_test_name = '';
			}


			$qry1 = "SELECT count(chemist_code)
						FROM final_test_result AS ftr
						INNER JOIN sample_inward AS si ON si.org_sample_code=ftr.org_sample_code AND si.result_dupl_flag='D' AND ftr.sample_code='$sample_code1'
						GROUP BY chemist_code ";

			$res2	= $conn->execute($qry1);
			$res2 = $res2->fetchAll('assoc');

			//get sample type code from sample sample inward table, to check if sample type is "Challenged"
			//if sample type is "challenged" then get report for selected final values only, no matter if single/duplicate analysis
			//applied on 27-10-2011 by Amol
			//$getSampleType = $this->SampleInward->find('all',array('fields'=>'sample_type_code','conditions'=>array('org_sample_code IS' => $Sample_code)))->first();
			//$sampleTypeCode = $getSampleType['sample_type_code'];

			if($sampleTypeCode==4){
				$res2=array();//this will create report for selected final results, if this res set to blank
			}

			$count_chemist = '';
			$all_chemist_code = array();


			//get al  allocated chemist if sample is for duplicate analysis
			if (isset($res2[0]['count'])>0) {

					$all_chemist_code = $conn->execute("SELECT ftr.chemist_code
														FROM m_sample_allocate AS ftr
														INNER JOIN sample_inward AS si ON si.org_sample_code=ftr.org_sample_code AND si.result_dupl_flag='D' AND ftr.sample_code='$sample_code1' ");

				$all_chemist_code= $all_chemist_code->fetchAll('assoc');

				$count_chemist = count($all_chemist_code);

			}

			//to get approved final result by Inward officer test wise
			$test_result= $this->FinalTestResult->find('list',array('valueField' => 'final_result','conditions' =>array('org_sample_code IS' => $Sample_code,'test_code' => $row,'display'=>'Y')))->toArray();

			//if sample is for duplicate analysis
			//so get result chmeist wise
			$result_D = '';
			$result = array();

			if (isset($res2[0]['count'])>0) {

				$i=0;

				foreach ($all_chemist_code as $each) {

					$chemist_code = $each['chemist_code'];

					//get result for each chemist_code
					$get_results = $this->ActualTestData->find('all',array('fields'=>array('result'),'conditions'=>array('org_sample_code IS' => $Sample_code,'chemist_code IS'=>$chemist_code,'test_code IS'=>$row,'display'=>'Y')))->first();

					$result[$i] = $get_results['result'];

					$i=$i+1;

				}


				//else get result from final test rsult
				//for single anaylsis this is fianl approved result array
			} else {

				if (count($test_result)>0) {

					foreach ($test_result as $key=>$val) {

						$result = $val;
					}
				} else {

					$result="";
				}
			}


			//for duplicate anaylsis this is final approved result array
			if (count($test_result)>0) {

				foreach ($test_result as $key=>$val) {
					$result_D= $val;
				}

			} else {
				$result_D="";
			}

			$commencement_date= $this->MSampleAllocate->find('all',array('order' => array('commencement_date' => 'asc'),'fields' => array('commencement_date'),'conditions' =>array('org_sample_code IS' => $Sample_code, 'display' => 'Y')))->toArray();
			$this->set('comm_date',$commencement_date[0]['commencement_date']);

			if (!empty($count_chemist)) {

				$count_chemist1 =  $count_chemist;
			} else {
				$count_chemist1 = '';
			}

			$this->set('count_test_result',$count_chemist1);


			$minMaxValue = '';

			foreach ($commo_grade as $key=>$val) {

				$key = $val['grade_code'];

				foreach ($data as $data4) {

					$data_grade_code = $data4['grade_code'];

					if ($data_grade_code == $key) {

						$grade_code_match = 'yes';

						if (trim($data4['min_max'])=='Min') {
							$minMaxValue = "<br>(".$data4['min_max'].")";
						}
						elseif (trim($data4['min_max'])=='Max') {
							$minMaxValue = "<br>(".$data4['min_max'].")";
						}
					}
				}

			}

			$str.="<tr><td>".$j."</td><td>".$data_test_name.$minMaxValue."</td>";
			//$sampleTypeCode = $getSampleType['sample_type_code'];/*  check the count of max value added on 01/06/2022 */

			if($sampleTypeCode!=8 && $sampleTypeCode!=9){/* if sample type food safety parameter & ILC could not show grade added on 01/06/2022  by shreeya */

				// Draw tested test reading values,
				foreach ($commo_grade as $key=>$val) {

					$key = $val['grade_code'];

					$grade_code_match = 'no';

					foreach ($data as $data4) {

						$data_grade_code = $data4['grade_code'];

						if ($data_grade_code == $key) {

							$grade_code_match = 'yes';

							if (trim($data4['min_max'])=='Range') {

								$str.="<td>".$data4['grade_value']."-".$data4['max_grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='Min') {

								$str.="<td>".$data4['grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='Max') {

								$str.="<td>".$data4['max_grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='-1') {

								$str.="<td>".$data4['grade_value']."</td>";

							}
						}
					}

					if ($grade_code_match == 'no') {
						$str.="<td>---</td>";
					}

				}

			}
			//for duplicate analysis chemist wise results
			if ($count_chemist1>0) {

				for ($g=0;$g<$count_chemist;$g++) {
					$str.="<td align='center'>".$result[$g]."</td>";
				}

				//for final result column
				$str.="<td align='center'>".$result_D."</td>";

			//for single analysis final results
			} else {
				// start for max val according to food sefety parameter added on 01/06/2022 by shreeya
				$str.="<td>".$result."</td>";
				if($sampleTypeCode==8){
					$max_val = $data[0]['max_grade_value'];
					$str.="<td>".$max_val."</td>";
				}
			    // end 01/06/2022
			}

			//$this->set('getSampleType',$getSampleType );

			$str.="<td>".$data_method_name."</td></tr>";
			$j++;
		}

		$this->set('table_str',$str);


		/*
		Starts here
		to bifurcate accredited and non accredited test parameters on report
		The conditional non accredited tests logic starts here for NABL non accredited test results.
		The code is repitition of the logic from above code.
		on 09-08-2022 by Amol
		*/
		foreach($test_string_ext as $row1) {

			$query = $conn->execute("SELECT DISTINCT(grade.grade_desc),grade.grade_code,test_code
										FROM comm_grade AS cg
										INNER JOIN m_grade_desc AS grade ON grade.grade_code = cg.grade_code
										WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row1' AND cg.display = 'Y'");

			$commo_grade = $query->fetchAll('assoc');
			$str2="";

			$this->set('commo_grade',$commo_grade );
		}

		$j=1;

		foreach ($test_string_ext as $row) {

			$query = $conn->execute("SELECT cg.grade_code,cg.grade_value,cg.max_grade_value,cg.min_max
										FROM comm_grade AS cg
										INNER JOIN m_test_method AS tm ON tm.method_code = cg.method_code
										INNER JOIN m_test AS t ON t.test_code = cg.test_code
										WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row' AND cg.display = 'Y'
										ORDER BY cg.grade_code ASC");

			$data = $query->fetchAll('assoc');


			$query = $conn->execute("SELECT t.test_name,tm.method_name
									 FROM comm_grade AS cg
									 INNER JOIN m_test_method AS tm ON tm.method_code = cg.method_code
									 INNER JOIN m_test AS t ON t.test_code = cg.test_code
									 INNER JOIN test_formula AS tf ON tf.test_code = cg.test_code AND tm.method_code = cg.method_code
									 WHERE cg.commodity_code = '$commodity_code' AND cg.test_code = '$row' AND cg.display = 'Y'
									 ORDER BY t.test_name ASC");

			$data1 = $query->fetchAll('assoc');

			if (!empty($data1)) {

				$data_method_name = $data1[0]['method_name'];
				$data_test_name = $data1[0]['test_name'];

			} else {

				$data_method_name = '';
				$data_test_name = '';
			}


			$qry1 = "SELECT count(chemist_code)
						FROM final_test_result AS ftr
						INNER JOIN sample_inward AS si ON si.org_sample_code=ftr.org_sample_code AND si.result_dupl_flag='D' AND ftr.sample_code='$sample_code1'
						GROUP BY chemist_code ";

			$res2	= $conn->execute($qry1);
			$res2 = $res2->fetchAll('assoc');

			//get sample type code from sample sample inward table, to check if sample type is "Challenged"
			//if sample type is "challenged" then get report for selected final values only, no matter if single/duplicate analysis
			//applied on 27-10-2011 by Amol
			//$getSampleType = $this->SampleInward->find('all',array('fields'=>'sample_type_code','conditions'=>array('org_sample_code IS' => $Sample_code)))->first();
			//$sampleTypeCode = $getSampleType['sample_type_code'];

			if($sampleTypeCode==4){
				$res2=array();//this will create report for selected final results, if this res set to blank
			}

			$count_chemist = '';
			$all_chemist_code = array();


			//get al  allocated chemist if sample is for duplicate analysis
			if (isset($res2[0]['count'])>0) {

					$all_chemist_code = $conn->execute("SELECT ftr.chemist_code
														FROM m_sample_allocate AS ftr
														INNER JOIN sample_inward AS si ON si.org_sample_code=ftr.org_sample_code AND si.result_dupl_flag='D' AND ftr.sample_code='$sample_code1' ");

				$all_chemist_code= $all_chemist_code->fetchAll('assoc');

				$count_chemist = count($all_chemist_code);

			}

			//to get approved final result by Inward officer test wise
			$test_result= $this->FinalTestResult->find('list',array('valueField' => 'final_result','conditions' =>array('org_sample_code IS' => $Sample_code,'test_code' => $row,'display'=>'Y')))->toArray();

			//if sample is for duplicate analysis
			//so get result chmeist wise
			$result_D = '';
			$result = array();

			if (isset($res2[0]['count'])>0) {

				$i=0;

				foreach ($all_chemist_code as $each) {

					$chemist_code = $each['chemist_code'];

					//get result for each chemist_code
					$get_results = $this->ActualTestData->find('all',array('fields'=>array('result'),'conditions'=>array('org_sample_code IS' => $Sample_code,'chemist_code IS'=>$chemist_code,'test_code IS'=>$row,'display'=>'Y')))->first();

					$result[$i] = $get_results['result'];

					$i=$i+1;

				}


				//else get result from final test rsult
				//for single anaylsis this is fianl approved result array
			} else {

				if (count($test_result)>0) {

					foreach ($test_result as $key=>$val) {

						$result = $val;
					}
				} else {

					$result="";
				}
			}


			//for duplicate anaylsis this is final approved result array
			if (count($test_result)>0) {

				foreach ($test_result as $key=>$val) {
					$result_D= $val;
				}
			} else {
				$result_D="";
			}

			$commencement_date= $this->MSampleAllocate->find('all',array('order' => array('commencement_date' => 'asc'),'fields' => array('commencement_date'),'conditions' =>array('org_sample_code IS' => $Sample_code, 'display' => 'Y')))->toArray();
			$this->set('comm_date',$commencement_date[0]['commencement_date']);

			if (!empty($count_chemist)) {

				$count_chemist1 =  $count_chemist;
			} else {
				$count_chemist1 = '';
			}

			$this->set('count_test_result',$count_chemist1);


			$minMaxValue = '';

			foreach ($commo_grade as $key=>$val) {

				$key = $val['grade_code'];

				foreach ($data as $data4) {

					$data_grade_code = $data4['grade_code'];

					if ($data_grade_code == $key) {

						$grade_code_match = 'yes';

						if (trim($data4['min_max'])=='Min') {
							$minMaxValue = "<br>(".$data4['min_max'].")";
						}
						elseif (trim($data4['min_max'])=='Max') {
							$minMaxValue = "<br>(".$data4['min_max'].")";
						}
					}
				}

			}

			$str2.="<tr><td>".$j."</td><td>".$data_test_name.$minMaxValue."</td>";
			//$sampleTypeCode = $getSampleType['sample_type_code'];/*  check the count of max value added on 01/06/2022 */

			if($sampleTypeCode!=8){/* if sample type food safety parameter added on 01/06/2022  by shreeya */

				// Draw tested test reading values,
				foreach ($commo_grade as $key=>$val) {

					$key = $val['grade_code'];

					$grade_code_match = 'no';

					foreach ($data as $data4) {

						$data_grade_code = $data4['grade_code'];

						if ($data_grade_code == $key) {

							$grade_code_match = 'yes';

							if (trim($data4['min_max'])=='Range') {

								$str2.="<td>".$data4['grade_value']."-".$data4['max_grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='Min') {

								$str2.="<td>".$data4['grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='Max') {

								$str2.="<td>".$data4['max_grade_value']."</td>";

							} elseif (trim($data4['min_max'])=='-1') {

								$str2.="<td>".$data4['grade_value']."</td>";

							}
						}
					}

					if ($grade_code_match == 'no') {
						$str2.="<td>---</td>";
					}

				}

			}
			//for duplicate analysis chemist wise results
			if ($count_chemist1>0) {

				for ($g=0;$g<$count_chemist;$g++) {
					$str2.="<td align='center'>".$result[$g]."</td>";
				}

				//for final result column
				$str2.="<td align='center'>".$result_D."</td>";

			//for single analysis final results
			} else {
				// start for max val according to food sefety parameter added on 01/06/2022 by shreeya
				$str2.="<td>".$result."</td>";
				if($sampleTypeCode==8){
					$max_val = $data[0]['max_grade_value'];
					$str2.="<td>".$max_val."</td>";
				}
			    // end 01/06/2022
			}
			//$this->set('getSampleType',$getSampleType );

			$str2.="<td>".$data_method_name."</td></tr>";
			$j++;
		}

		$this->set('table_str2',$str2 );
		/*
		Ends here
		The conditional non accredited tests logic ends here for NABL non accredited test results.
		The code is repitition of the logic from above code.
		on 09-08-2022 by Amol
		*/

		//added to by pass ilc report without grading
		//as in ilc there is no grading at all
		//on 11-08-2022 by shreeya
		if($sampleTypeCode == 9){

			$checktestallocation = "";
			$allocatefield = "";
			if(empty($checkifmainilc)){
				$allocatefield = "sa.sample_code,";
				$checktestallocation = "INNER JOIN m_sample_allocate AS sa ON sa.org_sample_code = si.org_sample_code";
			}

			$query = $conn->execute("SELECT si.*,mc.commodity_name, mcc.category_name, st.sample_type_desc, ct.container_desc, pc.par_condition_desc, uw.unit_weight, rf.ro_office,".$allocatefield." ur.user_flag, u1.f_name, u1.l_name, rf2.ro_office
								FROM sample_inward AS si
								INNER JOIN m_commodity AS mc ON mc.commodity_code = si.commodity_code
								INNER JOIN m_commodity_category AS mcc ON mcc.category_code = si.category_code
								INNER JOIN m_sample_type AS st ON st.sample_type_code = si.sample_type_code
								INNER JOIN m_container_type AS ct ON ct.container_code = si.container_code
								INNER JOIN m_par_condition AS pc ON pc.par_condition_code = si.par_condition_code
								INNER JOIN dmi_ro_offices AS rf ON rf.id = si.loc_id
								INNER JOIN dmi_ro_offices AS rf2 ON rf2.id = si.grade_user_loc_id
								INNER JOIN m_unit_weight AS uw ON uw.unit_id = si.parcel_size
								".$checktestallocation."
								INNER JOIN dmi_users AS u ON u.id = si.user_code
								INNER JOIN dmi_users AS u1 ON u1.id = si.grade_user_cd
								INNER JOIN dmi_user_roles AS ur ON u.email = ur.user_email_id
								WHERE si.org_sample_code = '$Sample_code' ");

			$test_report = $query->fetchAll('assoc');

			/* else forother sample types reports*/
		} else {

			$query = $conn->execute("SELECT si.*,mc.commodity_name, mcc.category_name, st.sample_type_desc, ct.container_desc, pc.par_condition_desc, uw.unit_weight, rf.ro_office, sa.sample_code, ur.user_flag, gd.grade_desc, u1.f_name, u1.l_name, rf2.ro_office
								FROM sample_inward AS si
								INNER JOIN m_commodity AS mc ON mc.commodity_code = si.commodity_code
								INNER JOIN m_commodity_category AS mcc ON mcc.category_code = si.category_code
								INNER JOIN m_sample_type AS st ON st.sample_type_code = si.sample_type_code
								INNER JOIN m_container_type AS ct ON ct.container_code = si.container_code
								INNER JOIN m_par_condition AS pc ON pc.par_condition_code = si.par_condition_code
								INNER JOIN dmi_ro_offices AS rf ON rf.id = si.loc_id
								INNER JOIN dmi_ro_offices AS rf2 ON rf2.id = si.grade_user_loc_id
								INNER JOIN m_unit_weight AS uw ON uw.unit_id = si.parcel_size
								INNER JOIN m_sample_allocate AS sa ON sa.org_sample_code = si.org_sample_code
								INNER JOIN dmi_users AS u ON u.id = si.user_code
								INNER JOIN dmi_users AS u1 ON u1.id = si.grade_user_cd
								INNER JOIN dmi_user_roles AS ur ON u.email = ur.user_email_id
								INNER JOIN m_grade_desc AS gd ON gd.grade_code = si.grade
								WHERE si.org_sample_code = '$Sample_code'");

			$test_report = $query->fetchAll('assoc');
		}

		if($test_report){

			$query = $conn->execute("SELECT ur.user_flag,office.ro_office,usr.email
									FROM workflow AS w
									INNER JOIN dmi_ro_offices AS office ON office.id = w.src_loc_id
									INNER JOIN dmi_users AS usr ON usr.id=w.src_usr_cd
									INNER JOIN dmi_user_roles AS ur ON usr.email= ur.user_email_id
									WHERE w.org_sample_code='$Sample_code'
									AND stage_smpl_flag IN('OF','HF')");

			$sample_forwarded_office = $query->fetchAll('assoc');

			$sample_final_date = $this->Workflow->find('all',array('fields'=>'tran_date','conditions'=>array('stage_smpl_flag'=>'FG','org_sample_code IS'=>$Sample_code)))->first();
			$sample_final_date['tran_date'] = date('d/m/Y');//taking current date bcoz creating pdf before grading for preview.

			//Customer Details on 05-08-2022 by akash
			$this->loadModel('LimsCustomerDetails');
			$customerDetails = $this->LimsCustomerDetails->find('all')->where(['org_sample_code IS' => $Sample_code])->first();
			if (!empty($customerDetails)) {
				$customer_details = $customerDetails;

				$stateAndDistrict = $conn->execute("SELECT ds.state_name,dd.district_name
													FROM lims_customer_details AS lcd
													INNER JOIN dmi_states AS ds ON ds.id = lcd.state
													INNER JOIN dmi_districts AS dd ON dd.id = lcd.district
													WHERE lcd.org_sample_code = '$Sample_code'")->fetch('assoc');
				if (!empty($stateAndDistrict)) {
					$this->set('stateAndDistrict',$stateAndDistrict);
				} else {
					$stateAndDistrict = null;
				}

			} else {
				$customer_details = null;
			}

			$this->set('sample_final_date',$sample_final_date['tran_date']);
			$this->set('sample_forwarded_office',$sample_forwarded_office);
			$this->set('test_report',$test_report);
			$this->set('customer_details',$customer_details);

			// Call to function for generate pdf file,
			// change generate pdf file name,
			$current_date = date('d-m-Y');
			$test_report_name = 'grade_report_'.trim($sample_code1).'.pdf';

			//store pdf path to sample inward table to preview further
			//store link only after esign done.
			//ajax condition added on 23-01-2023  By Shreya 
			if($this->request->is('ajax')){
				$pdf_path = '/testdocs/LIMS/reports/'.$test_report_name;
				$this->SampleInward->updateAll(array('report_pdf'=>"$pdf_path"),array('org_sample_code'=>$Sample_code));
			}

			$this->Session->write('pdf_file_name',$test_report_name);

			//Send parameter for Sample Test Report to getQrCodeSampleTestReport function
			// Author : Shankhpal Shende
			// Description : This will send parameter for QR code for Sample Test Report
			// Date : 01/09/2022
			$result_for_qr = $this->Customfunctions->getQrCodeSampleTestReport($Sample_code_as,$sample_forwarded_office,$test_report);
			$this->set('result_for_qr',$result_for_qr);

			//call to the pdf creation common method
			if($this->request->is('ajax')){//on consent check box click
				$this->EsigncallTcpdf($this->render(),'F',$test_report_name);//to save and store

			}else{//on preview link click
				$this->EsigncallTcpdf($this->render(),'I',$test_report_name);//to preview
			}


		}

	}

	//added for rediect to sub samples in ILC flow with non grading
	//inner sub samples done by shreeya on 02-08-2022
	public function ilcRedirectToVerify($verify_sample_code)
	{
		$this->Session->write('verify_sample_code',$verify_sample_code);
		$this->redirect(array('controller'=>'FinalGrading','action'=>'ilc_grading_by_inward'));
	}

	//added for inner sub sample
	//no grade by ilc flow show the new window done by shreeya on 02-08-2022
	public function ilcGradingByInward(){

		$this->authenticateUser();
		$this->viewBuilder()->setLayout('admin_dashboard');
		$str1		  = "";
		$this->loadModel('MCommodityCategory');
		$this->loadModel('DmiUsers');
		$this->loadModel('FinalTestResult');
		$this->loadModel('MGradeStandard');
		$this->loadModel('MTestMethod');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('MSampleAllocate');
		$this->loadModel('MCommodity');
		$this->loadModel('MGradeDesc');
		$conn = ConnectionManager::get('default');

		$verify_sample_code = $this->Session->read('verify_sample_code');

		if (!empty($verify_sample_code)) {

			$this->set('samples_list',array($verify_sample_code=>$verify_sample_code));
			$this->set('stage_sample_code',$verify_sample_code);//for hidden field, to use common script

			$grades_strd=$this->MGradeStandard->find('list',array('keyField'=>'grd_standrd','valueField'=>'grade_strd_desc','order' => array('grade_strd_desc' => 'ASC')))->toArray();
			$this->set('grades_strd',$grades_strd);

			$grades=$this->MGradeDesc->find('list',array('keyField'=>'grade_code','valueField'=>'grade_desc','order' => array('grade_desc' => 'ASC'),'conditions' => array('display' => 'Y')))->toArray();
			$this->set('grades',$grades);

			if ($this->request->is('post')) {

				$postdata = $this->request->getData();

				//html encode the each post inputs
				foreach($postdata as $key => $value){


					$postdata[$key] = htmlentities($this->request->getData($key), ENT_QUOTES);
				}

				if ($this->request->getData('button')=='add') {
					// Add new filed to add subgrading value
					$subGradeChecked = $this->request->getData('subgrade');

					$sample_code=$this->request->getData('sample_code');

					$category_code=$this->request->getData('category_code');
					$commodity_code=$this->request->getData('commodity_code');
					$remark=$this->request->getData('remark');

					if (null !== ($this->request->getData('result_flg'))) {
						$result_flg	= $this->request->getData('result_flg');
					}
					else {
						$result_flg="";
					}
					$flagArr = array("P", "F", "M","R");

					$result_grade	=	'';
					$grade_code_vs=$this->request->getData('grade_code');

					$tran_date=$this->request->getData("tran_date");
					$ogrsample1= $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $sample_code)))->first();
					$ogrsample=$ogrsample1['org_sample_code'];


					$src_usr_cd = $conn->execute("SELECT src_usr_cd FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='TA' ");
					$src_usr_cd = $src_usr_cd->fetchAll('assoc');
					$abc = $src_usr_cd[0]['src_usr_cd'];

					$test_n_r_no = $conn->execute("SELECT max(test_n_r_no) FROM m_sample_allocate WHERE sample_code='$sample_code' AND test_n_r='R' ");
					$test_n_r_no = $test_n_r_no->fetchAll('assoc');
					$abc1 = $test_n_r_no[0]['max']+1;

					if ($result_flg=='R') {

						$_SESSION["loc_id"] =$_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] =$_SESSION["user_code"];

						$workflow_data = array("org_sample_code"=>$ogrsample,
											"src_loc_id"=>$_SESSION["posted_ro_office"],
											"src_usr_cd"=>$_SESSION["user_code"],
											"dst_loc_id"=>$_SESSION["posted_ro_office"],
											"dst_usr_cd"=>$abc,"stage_smpl_flag"=>"R",
											"tran_date"=>$tran_date,
											"user_code"=>$_SESSION["user_code"],
											"stage_smpl_cd"=>$sample_code,  "stage"=>"8");

						$workflowEntity =  $this->Workflow->newEntity($workflow_data);

						$this->Workflow->save($workflowEntity);


						$dst_usr_cd = $conn->execute("SELECT dst_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='R' ");
						$dst_usr_cd = $dst_usr_cd->fetchAll('assoc');

						$abcd = $dst_usr_cd[0]['dst_usr_cd'];

						$user_name = $conn->execute("SELECT DISTINCT role FROM dmi_users AS u
													INNER JOIN workflow AS w ON u.id = w.dst_usr_cd
													INNER JOIN user_role AS r ON u.role = r.role_name
													WHERE dst_usr_cd ='$abcd'
													AND org_sample_code='$ogrsample'
													AND stage_smpl_flag='R'");

						$user_name = $user_name->fetchAll('assoc');

						$abc2 = $user_name[0]['role'];

						$_SESSION["loc_id"] =$_SESSION["posted_ro_office"];

						$_SESSION["loc_user_id"] =$_SESSION["user_code"];

						$date=date("Y/m/d");

						$sample_code=trim($this->request->getData('sample_code'));


						$query = $conn->execute("SELECT si.org_sample_code
												FROM sample_inward AS si
												INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($result_flg =='F') {

							$result_flg='Fail';

						} elseif ($result_flg=='M') {

							$result_flg='Misgrade';

						} else {

							$result_flg='SR';
						}

						// Add two new fileds to add subgrading value and inward grading date ,

						$conn->execute("UPDATE sample_inward SET remark ='$remark', status_flag ='R', grade ='$grade_code_vs', grading_date ='$date', inward_grading_date = '$date', sub_grad_check_iwo = '$subGradeChecked', inward_grade = '$grade_code_vs'
										WHERE category_code = '$category_code' AND commodity_code = '$commodity_code' AND org_sample_code = '$ogrsample_code' AND display = 'Y' ");

						//call to the common SMS/Email sending method
						$this->loadModel('DmiSmsEmailTemplates');
						//$this->DmiSmsEmailTemplates->sendMessage(2016,$sample_code);

						echo '#The sample is marked for retest and re-sent to '.$abc2.'#';

						exit;

					}
					else
					{

						$dst_loc =$_SESSION["posted_ro_office"];

						if ($_SESSION['user_flag']=='RAL') {

							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];

						} else {

							/* Change the conditions for to find destination user id, after test result approved by lab inward officer the application send to RAL/CAL OIC officer */
							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];
						}


						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

									$workflow_data = array("org_sample_code"=>$ogrsample,
															"src_loc_id"=>$_SESSION["posted_ro_office"],
															"src_usr_cd"=>$_SESSION["user_code"],
															"dst_loc_id"=>$_SESSION["posted_ro_office"],
															"dst_usr_cd"=>$dst_usr,
															"stage_smpl_flag"=>"FS",
															"tran_date"=>$tran_date,
															"user_code"=>$_SESSION["user_code"],
															"stage_smpl_cd"=>$sample_code,
															"stage"=>"8");
							} else {

									// Change the stage_smpl_flag value FG to FGIO to genreate the sample report after grading by OIC,
									$workflow_data = array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"FGIO",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"8");
							}

						} elseif ($_SESSION['user_flag']=='CAL') {

							if (trim($result_flg)=='F') {

								$workflow_data =  array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"FC",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"7");

							} else {

								$workflow_data =  array("org_sample_code"=>$ogrsample,
														"src_loc_id"=>$_SESSION["posted_ro_office"],
														"src_usr_cd"=>$_SESSION["user_code"],
														"dst_loc_id"=>$_SESSION["posted_ro_office"],
														"dst_usr_cd"=>$dst_usr,
														"stage_smpl_flag"=>"VS",
														"tran_date"=>$tran_date,
														"user_code"=>$_SESSION["user_code"],
														"stage_smpl_cd"=>$sample_code,
														"stage"=>"7");
							}
						}

						$workflowEntity = $this->Workflow->newEntity($workflow_data);

						$this->Workflow->save($workflowEntity);

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];

						$_SESSION["loc_user_id"] = $_SESSION["user_code"];

						$date = date("Y/m/d");

						$sample_code = trim($this->request->getData('sample_code'));


						$query = $conn->execute("SELECT si.org_sample_code
												FROM sample_inward AS si
												INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FS',

																		remark ='$remark',
																		grading_date='$date',
																		inward_grading_date='$date',
																		sub_grad_check_iwo='$subGradeChecked',
																		inward_grade='$grade_code_vs',
																		grade_user_cd=".$_SESSION['user_code'].",
																		grade_user_flag='".$_SESSION['user_flag']."',
																		grade_user_loc_id='".$_SESSION['posted_ro_office']."',
																		ral_anltc_rslt_rcpt_dt='$tran_date'
																		WHERE category_code= '$category_code'
																		AND commodity_code = '$commodity_code'
																		AND org_sample_code = '$ogrsample_code'
																		AND display = 'Y' ");

							} else {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FG',
																		remark ='$remark',
																		grading_date='$date',
																		inward_grading_date='$date',
																		sub_grad_check_iwo='$subGradeChecked',
																		inward_grade='$grade_code_vs',
																		grade_user_cd=".$_SESSION['user_code'].",
																		grade_user_flag='".$_SESSION['user_flag']."',
																		grade_user_loc_id='".$_SESSION['posted_ro_office']."',
																		ral_anltc_rslt_rcpt_dt='$tran_date'
																		WHERE category_code= '$category_code'
																		AND commodity_code = '$commodity_code'
																		AND org_sample_code = '$ogrsample_code'
																		AND display = 'Y' ");
							}

					} elseif ($_SESSION['user_flag']=='CAL') {

						if ($result_flg=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FC',
																		remark ='$remark',
																		grading_date='$date',
																		inward_grading_date='$date',
																		sub_grad_check_iwo='$subGradeChecked',
																		inward_grade='$grade_code_vs',
																		grade_user_cd=".$_SESSION['user_code'].",
																		grade_user_flag='".$_SESSION['user_flag']."',
																		grade_user_loc_id='".$_SESSION['posted_ro_office']."',
																		ral_anltc_rslt_rcpt_dt='$tran_date'
																		WHERE category_code= '$category_code'
																		AND commodity_code = '$commodity_code'
																		AND org_sample_code = '$ogrsample_code'
																		AND display = 'Y' ");

							} else {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='VS',
																		remark ='$remark',
																		grading_date='$date',
																		inward_grading_date='$date',
																		sub_grad_check_iwo='$subGradeChecked',
																		inward_grade='$grade_code_vs',
																		grade_user_cd='".$_SESSION['user_code']."',
																		grade_user_flag='".$_SESSION['user_flag']."',
																		grade_user_loc_id='".$_SESSION['posted_ro_office']."',
																		cal_anltc_rslt_rcpt_dt='$tran_date'
																		WHERE category_code= '$category_code'
																		AND commodity_code = '$commodity_code'
																		AND org_sample_code = '$ogrsample_code'
																		AND display = 'Y' ");
							}
						}


						if ($_SESSION['user_flag']=='RAL') {

							#SMS: Sample Finalized By Inward
							$this->DmiSmsEmailTemplates->sendMessage(109,$_SESSION["user_code"],$sample_code); 	#RAL
							$this->DmiSmsEmailTemplates->sendMessage(110,$dst_usr,$sample_code); 				#OIC
							$this->LimsUserActionLogs->saveActionLog('Sample Finalized Sent to RAL','Success'); #Action

							echo '#The results have been finalized and forwarded to RAL,Office Incharge#';
							exit;

						} elseif ($_SESSION['user_flag']=='CAL') {

							#SMS: Sample Finalized By Inward
							$this->DmiSmsEmailTemplates->sendMessage(111,$_SESSION["user_code"],$sample_code); 	#CAL
							$this->DmiSmsEmailTemplates->sendMessage(110,$dst_usr,$sample_code); 				#OIC
							$this->LimsUserActionLogs->saveActionLog('Sample Finalized Sent to CAL','Success'); #Action

							echo '#The results have been finalized and forwarded to CAL,Office Incharge#';
							exit;
						} else {
							echo '#Record Save Sucessfully!#';
							exit;
						}
					}
				}
			}
		}
	}

	//added for ilcFlow  08-07-2022
	//redirdction for sub samples of inner oic window
	public function redirectToGradeIlc($grading_sample_code){

		$this->Session->write('grading_sample_code',$grading_sample_code);
		$this->redirect(array('controller'=>'FinalGrading','action'=>'ilc_grading_by_oic'));
	}

/******************************************************************************************************************************************************************************************************/
	// added for ilcFlow 08-07-2022 Done By Shreeya
	//for sub samples of inner oic window view
	public function ilcGradingByOic(){

		$this->authenticateUser();
		$this->viewBuilder()->setLayout('admin_dashboard');
		$str1		  = "";
		$this->loadModel('MCommodityCategory');
		$this->loadModel('DmiUsers');
		$this->loadModel('FinalTestResult');
		$this->loadModel('MGradeStandard');
		$this->loadModel('MTestMethod');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('MSampleAllocate');
		$this->loadModel('MCommodity');
		$this->loadModel('MGradeDesc');
		$conn = ConnectionManager::get('default');

		$grading_sample_code = $this->Session->read('grading_sample_code');

		if(!empty($grading_sample_code)){

			$this->set('samples_list',array($grading_sample_code=>$grading_sample_code));
			$this->set('stage_sample_code',$grading_sample_code);//for hidden field, to use common script

			//get org samle code
			$ogrsample1= $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $grading_sample_code)))->first();
			$ogrsample = $ogrsample1['org_sample_code'];


			//to get commodity code for report pdf
			$getcommoditycd = $this->SampleInward->find('all',array('fields'=>'commodity_code','conditions'=>array('org_sample_code IS'=>$ogrsample),'order'=>'inward_id desc'))->first();
			$smple_commdity_code = $getcommoditycd['commodity_code'];
			$this->set('smple_commdity_code',$smple_commdity_code);

			if ($this->request->is('post')) {

				//html encode the each post inputs
				$postdata = $this->request->getData();

				foreach ($postdata as $key => $value) {

					$data[$key] = htmlentities($this->request->getData($key), ENT_QUOTES);
				}

				$sample_code = $this->request->getData('sample_code');

				if ($this->request->getData('button')=='add') {

					$category_code=$this->request->getData('category_code');

					$commodity_code=$this->request->getData('commodity_code');

					$remark=$this->request->getData('remark');

					$remark_new=$this->request->getData('remark_new');


					if (null!==($this->request->getData('result_flg'))) {

						$result_flg	= $this->request->getData('result_flg');

					} else {

						$result_flg="";
					}

					$result_grade	=	'';
					$grade_code_vs=$this->request->getData('grade_code');

					$tran_date=$this->request->getData("tran_date");

					if ($result_flg=='R') {

						$src_usr_cd = $conn->execute("SELECT src_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' AND stage_smpl_flag='TA' ");
						$src_usr_cd = $src_usr_cd->fetchAll('assoc');
						$abc = $src_usr_cd[0]['src_usr_cd'];

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] = $_SESSION["user_code"];

						$workflow_data = array("org_sample_code"=>$ogrsample,
												"src_loc_id"=>$_SESSION["posted_ro_office"],
												"src_usr_cd"=>$_SESSION["user_code"],
												"dst_loc_id"=>$_SESSION["posted_ro_office"],
												"dst_usr_cd"=>$abc,
												"stage_smpl_flag"=>"R",
												"tran_date"=>$tran_date,
												"user_code"=>$_SESSION["user_code"],
												"stage_smpl_cd"=>$sample_code,
												"stage"=>"8");

						$workflowEntity = $this->Workflow->newEntity($workflow_data);
						$this->Workflow->save($workflowEntity);

						$dst_usr_cd = $conn->execute("SELECT dst_usr_cd  FROM workflow WHERE org_sample_code='$ogrsample' and stage_smpl_flag='R' ");

						$dst_usr_cd = $dst_usr_cd->fetchAll('assoc');
						$abcd = $dst_usr_cd[0]['dst_usr_cd'];

						$user_name = $conn->execute("SELECT DISTINCT role
													 FROM dmi_users AS u
													 INNER JOIN workflow AS w ON u.id=w.dst_usr_cd
											         INNER JOIN user_role AS r ON u.role=r.role_name
											         WHERE dst_usr_cd='$abcd' AND org_sample_code='$ogrsample' AND stage_smpl_flag='R' ");

						$user_name = $user_name->fetchAll('assoc');

						$abc2 = $user_name[0]['role'];

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];
						$_SESSION["loc_user_id"] = $_SESSION["user_code"];
						$date=date("Y/m/d");
						$sample_code = trim($this->request->getData('sample_code'));


						$query = $conn->execute("SELECT si.org_sample_code
												 FROM sample_inward AS si
												 INNER JOIN workflow AS w ON w.org_sample_code = si.org_sample_code
												 WHERE w.stage_smpl_cd = '$sample_code'");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];

						if ($result_flg=='F') {

							$result_flg='Fail';
						} elseif ($result_flg=='M') {

							$result_flg='Misgrade';

						} else {
							$result_flg='Retest';
						}

						 // Add two new fileds to add subgrading value and oic grading date ,
						$conn->execute("UPDATE sample_inward SET remark ='$result_flg',
			 													 remark_officeincharg ='$remark_new',
									 							 status_flag='SR',grade='$grade_code_vs',
																 grading_date='$date',
									 							 oic_grading_date='$date',
															     sub_grad_check_oic='$subGradeChecked'
															 WHERE category_code= '$category_code'
															 AND commodity_code = '$commodity_code'
															 AND org_sample_code = '$ogrsample_code'
															 AND display = 'Y' ");

						 //removed extra ',' from above code, it was getting error.

						 #SMS: Sample Marked For Retest By OIC
						$this->DmiSmsEmailTemplates->sendMessage(118,$_SESSION["user_code"],$sample_code); 	#OIC
						$this->DmiSmsEmailTemplates->sendMessage(119,$abc,$sample_code); 					#INWARD
						$this->LimsUserActionLogs->saveActionLog('Sample Sent For Retest','Success'); 		#Action

						echo '#0#';  // return 0 value to show conformation message
						exit;
					} else {
						//code moved to below new function save grading
					}
				}
			}
		}
	}

	//outer main sample list of report z score model
	public function ilcFinalizedSamples(){

		$final_reports = $this->ilcSampleTestReports();
		$this->set('ilc_sample_reports',$final_reports);
	}

	//outer sample for ilc to  show the list ilc finalized samples main sample
	// create new fun for showing ilc finalized sample done 13/07-2022 by shreeya
	public function ilcSampleTestReports(){


		$this->viewBuilder()->setLayout('admin_dashboard');
		$this->loadModel('Workflow');
		$this->loadModel('IlcOrgSmplcdMaps');
		$conn = ConnectionManager::get('default');

		//fetch the list of org sample code with OF flag done 13-07-2022 by shreeya
		$query2 = $conn->execute("SELECT si.org_sample_code, w.stage_smpl_cd,mcc.category_name,mc.commodity_name, st.sample_type_desc, w.tran_date,w.stage_smpl_flag,si.status_flag
				FROM sample_inward AS si
				INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
				INNER JOIN m_commodity_category AS mcc ON mcc.category_code = si.category_code
				INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
				INNER JOIN workflow AS w ON w.org_sample_code=si.org_sample_code
				WHERE  w.stage_smpl_flag='OF' AND si.status_flag='F' AND si.sample_type_code=9 AND w.stage_smpl_cd NOT IN ('','blank') AND si.entry_type IS NULL ");
				// stage_smpl_cd !='' condition added for empty sample code
				// by shankhpal shende on 19/04/2023   
		$result = $query2->fetchAll('assoc');
		$i=0;
		foreach ($result as $each) {

			//orignal code
			$getSavedList = $this->IlcOrgSmplcdMaps->find('all',array('conditions'=>array('org_sample_code IS'=>$each['org_sample_code'],'status IS'=>'1')))->toArray();

			foreach ($getSavedList as  $each1) {
				//new generated mapping code with FG flag
				$getdList = $this->Workflow->find('all',array('conditions'=>array('org_sample_code IS'=>$each1['ilc_org_sample_cd'],'stage_smpl_flag IS'=>'FG')))->toArray();

				if(empty($getdList)){

					unset($result[$i]);
					break;
				}
			}
			$i++;
		}

		$this->set('ilc_sample_reports',$result);

		return $result;

	}

	public function ilcSampleZscore($sample_code){

		$arraylist = $this->ilcAvailableSampleZscore($sample_code);
		$this->set('final_reports',$arraylist);
	}


	//outer sample show the list of sub sample report ande zscore button.
	// final result submited Zscore & report list 14-07-2022
	public function ilcAvailableSampleZscore()
	{

		//$this->viewBuilder()->setLayout('admin_dashboard');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('IlcOrgSmplcdMaps');
		$this->loadModel('ActualTestData');
		$this->loadmodel('IlcCalculateZscores');
		$this->loadmodel('IlcFinalZscores');
		$this->loadmodel('CommodityTest');
		$this->loadmodel('IlcSaveTestParameters');
		$this->loadModel('FinalTestResult');
		$this->loadModel('MTest');
		$this->loadComponent('Ilc');


		//$user_flag = $_SESSION['user_flag'];
		$message = '';
		$message_theme = '';
		$redirect_to = '';


		$conn = ConnectionManager::get('default');

		$sample_code=$this->Session->read('grading_sample_code');

		$this->set('samples_list',array($sample_code=>$sample_code));
		$this->set('stage_sample_code',$sample_code);//for hidden field, to use common script

		//fetch commodity , category_code ,org sample code $ sample_type done 19-07-2022 by shreeya
		$query = $conn->execute("SELECT sm.org_sample_code,mcc.category_name,mc.commodity_name,mc.commodity_code,st.sample_type_desc
								FROM ilc_org_smplcd_maps AS sm
					 			INNER JOIN workflow AS w ON sm.org_sample_code=w.org_sample_code
								INNER JOIN sample_inward AS si ON w.org_sample_code=si.org_sample_code
								INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
								INNER JOIN m_commodity_category AS mcc ON mcc.category_code =si.category_code
								INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code

					 			WHERE  w.stage_smpl_flag='SI' AND sm.org_sample_code='$sample_code' ");
		$getcommodity = $query->fetch('assoc');

		$this->set('getcommodity',$getcommodity);


		// above query added for fetch 'OF' list for ilc sample done 18-07-2022 by shreeya
		$query1 = $conn->execute("SELECT sm.ilc_org_sample_cd,w.stage_smpl_cd,w.tran_date,ro.ro_office,ro.office_type,si.report_pdf,sm.final_zscore,sm.calculate_zscore
								FROM ilc_org_smplcd_maps AS sm
					 			INNER JOIN workflow AS w ON sm.ilc_org_sample_cd=w.org_sample_code
					 			INNER JOIN dmi_ro_offices AS ro ON ro.id=w.dst_loc_id
								INNER JOIN sample_inward AS si ON w.org_sample_code=si.org_sample_code
					 			-- added in where condition 'IF' and si.entry_type='sub_sample' in that ilc module show the list of forwarded to lab incharge 
								--By- Shreeya - Date[03-05-2023]
								WHERE w.stage_smpl_flag IN ('OF','IF') AND sm.org_sample_code='$sample_code' AND sm.status = 1 AND si.entry_type='sub_sample'
								ORDER by sm.id ASC");
								
		$result = $query1->fetchAll('assoc');
		$this->set('result',$result);

		$i=1;
		$arraylist= array();
		foreach ($result as $each) {

			//fetch date according to FG flag date's
			$getList = $this->Workflow->find('all',array('conditions'=>array('stage_smpl_cd'=>$each['stage_smpl_cd'],'stage_smpl_flag IS'=>'FG')))->first();
			$arraylist[$i] = $getList['tran_date'];
			$i++;
		}

		$this->set('final_reports',$arraylist);



		//added for fetch test code & commodity code  on 08-08-2022 by shreeya
		//for get zscore pop up
		$test = $this->IlcSaveTestParameters->find('all', array('fields' => array('testname'),'conditions' =>array('sample_code IS' => $sample_code,'status IS'=>'1'),'order'=>'id ASC'))->toArray();
		$smplList = $this->IlcOrgSmplcdMaps->find('all', array('fields' => array('ilc_org_sample_cd'),'conditions' =>array('org_sample_code IS' => $sample_code,'status IS'=>'1'),'order'=>'id ASC'))->toArray();
	

		//added for fetch test name
		$testnames = array();
		$i=0;
		foreach($test as $row1) {

			$gettestnames = $this->MTest->find('all', array('fields' => array('test_name'),'conditions' =>array('test_code IN' => $row1['testname'], 'display' => 'Y')))->first();

			$testnames[$i] =$gettestnames['test_name'];

			$i++;
		}

		$this->set('testnames',$testnames);
		$this->set('testparameter', json_encode($testnames, true));/* [Date : 04-04-2023 By Shreeya]*/
		
		// added the foreach loop for check the test and lab wise data avalble or not
		// [Date : 04-04-2023 By Shreeya]
		if (isset($test)) {	

			$j=1;		
			$i=0;	
			foreach ($test as $eachtest) { 
			
				$l=0;
					$lablist = array();
					foreach($smplList as $eachoff){
						
						$lablist[$l] = 'forlab';
				
					 $l++;	
					} 

			$i++; $j++; 

			//in live project it shows undefined lablist so added this line under the condition- Shreeya - Date[03-05- 2023]
			$this->set('lablist', json_encode($lablist, true));
			} 
		} 

		//calculation of mean value 
		//according to zscore formula  on 10-08-2022 by Shreeya
		$i=0;
		$meanvalue = array();
		$resltarr = array();
		foreach($test as $row1) {

			$sumoftest = 0;
			$j=0;
			foreach($smplList as $sample) {

				$finalresult = $this->FinalTestResult->find('all', array('fields' => array('final_result'),'conditions' =>array('test_code IS' => $row1['testname'],'org_sample_code IS'=>$sample['ilc_org_sample_cd'],'display'=>'Y')))->first();

				if(!empty($finalresult)){
					//added is_numeric func to check value is numeric Date : 03-02-2023
					if(is_numeric($finalresult['final_result']) == true)
					{

						$sumoftest = $sumoftest + $finalresult['final_result'];

					}
					$resltarr[$i][$j] = $finalresult['final_result'];

				}
				$j++;

			}
			if($sumoftest > 0){

				$meanvalue[$i] = $sumoftest / count($smplList);

			}
			$i++;
		}

		// added for fetch saved value of zscore by shreeya on Date : 05-04-2023
		$finalZscore = $this->IlcFinalZscores->find('all', array('fields' => array('zscore'),'conditions' =>array('org_sample_code IS' => $sample_code,'status IS'=>'1')))->first();
	
		//calculation of zscore on 10-08-2022 by Shreeya
		$i=0;
		$zscorearr = array();
		$org_val = array();
		foreach($test as $row1) {

			$j=0;
			$zscore_cal = array();


			//call to ilc component for calculating the standard deviation
			// Date: 28-02-2023  By Shreeya
			$standard_deviation = $this->Ilc->getStandardDev($sample_code,$resltarr[$i]);

			foreach($smplList as $sample) {

				$finalresult = $this->FinalTestResult->find('all', array('fields' => array('final_result'),'conditions' =>array('test_code IS' => $row1['testname'],'org_sample_code IS'=>$sample['ilc_org_sample_cd'],'display'=>'Y')))->first();
				
				//added is_numeric func to check value is numeric
				if(!empty($finalresult && is_numeric($finalresult['final_result']))){
					
					if(!empty($meanvalue[$i])){
						//added if result integer date - 19-04-2023
						if( is_numeric($standard_deviation)){
							$zscore_cal[$j] = ($finalresult['final_result'] - $meanvalue[$i])/$standard_deviation;
							
						}
						//further condition added on 25-04-2023
						else{

							$zscore_cal[$j] = $finalZscore['zscore'];
							
						}

					}else{
						//further condition added on 25-04-2023
						$zscore_cal[$j] = $finalZscore['zscore'];
						
					}

					$org_val[$i][$j] = $finalresult['final_result'];
					
					
				}
				else{

					$zscore_cal[$j] = $finalZscore['zscore'];
					$org_val[$i][$j] = $finalresult['final_result'];
					 
				
				}

				$j++;
				
			}
			
			if(!empty($zscore_cal)){

				$zscorearr[$i] = $zscore_cal;
			}
			$i++;

		}

		$this->set('testarr',$test);
		$this->set('smplList',$smplList);
		$this->set('zscorearr',$zscorearr);
		$this->set('org_val',$org_val);



		//added to saving zscore result on 11-08-2022 by Shreeya
		// $i=0;
		// $zscorearr = array();
		// $savezscore  = array();

		// //call to ilc component for calculating the standard deviation
		// // Date: 28-02-2023  By Shreeya
		// $standard_deviation = $this->Ilc->getStandardDev($sample_code,$resltarr[$i]);
	
		// foreach($test as $row1) {

		// 	$j=0;
		// 	$zscore_cal = array();
		// 	foreach($smplList as $sample) {
				
		// 		$finalresult = $this->FinalTestResult->find('all', array('fields' => array('final_result'),'conditions' =>array('test_code IS' => $row1['testname'],'org_sample_code IS'=>$sample['ilc_org_sample_cd'],'display'=>'Y')))->first();
				
				
		// 		if(!empty($finalresult && is_numeric($finalresult['final_result']))){
		
		// 			if(!empty($meanvalue[$i])){
		// 				//added if result integer date - 25-04-2023
		// 				if( is_numeric($standard_deviation)){
		// 					$zscore_cal[$j] = ($finalresult['final_result'] - $meanvalue[$i])/$standard_deviation;
		// 				}
		// 				//further condition 25-04-2023
		// 				else{
		// 					//change zscore result finalZscore replace finalresult 
		// 					//zscore result is display in a  FinalTestResult table
		// 					//By -  Shreeya- Date[03-05-2023]
		// 					$zscore_cal[$j] = $finalresult['final_result'];
		// 				}
		// 			}
		// 			else{
		// 				$zscore_cal[$j] = $finalZscore['zscore']; 
		// 			}
						
		// 		}else{
		// 			$zscore_cal[$j] = $finalZscore['zscore'];
		// 		}
				
		// 		//check in new table if records exist with the sample code and status 1
		// 		$savedFinalZscore = $this->IlcFinalZscores->find('all', array('fields' => array('zscore'),'conditions' =>array('org_sample_code IS' => $sample_code),'status'=>'1','order'=>'id ASC'))->toArray();
			
		// 		//if yes then use new table name as model
		// 		//else use old table name as model
		// 		if(!empty($savedFinalZscore)){
		// 			$mymodelname = 'IlcFinalZscores';
		// 		}else{
		// 			$mymodelname = 'IlcCalculateZscores';
		// 		}
		// 		$this->loadModel($mymodelname);

		// 		$date = date('Y-m-d H:i:s');
		// 		//added a line for updated status according to status (0,1) done 05/04/2023 by shreeya
		// 		//$this->$mymodelname->updateAll(array('status' => 0,'modified'=>"$date"),array('org_sample_code' => $sample_code));
				
		// 		$savezscore[] = array(

		// 			'org_sample_code' 	=> $sample_code,
		// 			'zscore'			=> $zscore_cal[$j],
		// 			'created'			=> $date,
		// 			'modified'			=> $date,
		// 			'org_val'			=> $org_val[$i][$j],
		// 			'test_name'			=> $row1['testname'],
		// 			'status'			=> 1,

		// 			// extra
		// 			'sample_code'     	=> $sample['ilc_org_sample_cd'],
		// 			'lab_name'		  	=> 'test',
		// 			'test_code'		  	=> $row1['testname'],
		// 			'commodity_code'  	=> $getcommodity['commodity_code']
		// 		);
		// 		$j++;
		// 	}

		// 	if(!empty($zscore_cal)){

		// 		$zscorearr[$i] = $zscore_cal;
		// 	}
		// 	$i++;
		// }
		// //creating entities for array
		// $ZscorEntity = $this->$mymodelname->newEntities($savezscore);
		// //saving data in loop
		// foreach($ZscorEntity as $list){	

		// 	$this->$mymodelname->save($list);	
		// }  		
		
		// $calresult = $this->$mymodelname->find('all', array('conditions'=> array('sample_code IS' => $sample['ilc_org_sample_cd'])))->first();
		//$smpl_cd = $calresult['sample_code'];
		
		// if(!empty($calresult) ){
		if(!empty($zscore_cal) ){

			//added to finalized zscore forwarded to oic on 27-07-2022 by shreeya
			if ($this->request->is('post')) {

				if (null !==($this->request->getData('frd_to_oic'))) {

						$sample_code=$this->request->getData('sample_code');

						$category_code=$this->request->getData('category_code');
						$commodity_code=$this->request->getData('commodity_code');

						$remark=$this->request->getData('remark');

						if (null !== ($this->request->getData('result_flg'))) {
							$result_flg	= $this->request->getData('result_flg');
						}
						else {
							$result_flg="";
						}

						$flagArr = array("P", "F", "M","R");

						//$tran_date=$this->request->getData("tran_date");
						$ogrsample1= $this->Workflow->find('all', array('conditions'=> array('stage_smpl_cd IS' => $sample_code)))->first();
						$tran_date=$ogrsample1['tran_date'];
						$tran_date=date('Y-m-d');


						$dst_loc =$_SESSION["posted_ro_office"];

						if ($_SESSION['user_flag']=='RAL') {

							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office IS' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];

						} else {

							/* Change the conditions for to find destination user id, after test result approved by lab inward officer the application send to RAL/CAL OIC officer */
							$data = $this->DmiUsers->find('all', array('conditions'=> array('role' =>'RAL/CAL OIC','posted_ro_office IS' => $dst_loc,'status !='=>'disactive')))->first();
							$dst_usr = $data['id'];
						}

						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

								$workflow_data = array(

									"org_sample_code"	=>	$sample_code,
									"src_loc_id"		=>	$_SESSION["posted_ro_office"],
									"src_usr_cd"		=>	$_SESSION["user_code"],
									"dst_loc_id"		=>	$_SESSION["posted_ro_office"],
									"dst_usr_cd"		=>	$dst_usr,
									"stage_smpl_flag"	=>	"FS",
									"tran_date"			=>	$tran_date,
									"user_code"			=>	$_SESSION["user_code"],
									"stage_smpl_cd"		=>	$sample_code,
									"stage"				=>	"8");
							} else
							{

								// Change the stage_smpl_flag value FG to FGIO to genreate the sample report after grading by OIC,
								$workflow_data = array(

								"org_sample_code"	=>	$sample_code,
								"src_loc_id"		=>	$_SESSION["posted_ro_office"],
								"src_usr_cd"		=>	$_SESSION["user_code"],
								"dst_loc_id"		=>	$_SESSION["posted_ro_office"],
								"dst_usr_cd"		=>	$dst_usr,
								"stage_smpl_flag"	=>	"FGIO",
								"tran_date"			=>	$tran_date,
								"user_code"			=>	$_SESSION["user_code"],
								"stage_smpl_cd"		=>	$sample_code,
								"stage"				=>	"8");

							}

						}
						elseif ($_SESSION['user_flag']=='CAL')

						{

							if (trim($result_flg)=='F') {

								$workflow_data =  array(

								"org_sample_code"		=>	$sample_code,
								"src_loc_id"			=>	$_SESSION["posted_ro_office"],
								"src_usr_cd"			=>	$_SESSION["user_code"],
								"dst_loc_id"			=>	$_SESSION["posted_ro_office"],
								"dst_usr_cd"			=>	$dst_usr,
								"stage_smpl_flag"		=>	"FC",
								"tran_date"				=>	$tran_date,
								"user_code"				=>	$_SESSION["user_code"],
								"stage_smpl_cd"			=>	$sample_code,
								"stage"					=>	"7");

							} else {

								$workflow_data =  array(

								"org_sample_code"		=>	$sample_code,
								"src_loc_id"			=>	$_SESSION["posted_ro_office"],
								"src_usr_cd"			=>	$_SESSION["user_code"],
								"dst_loc_id"			=>	$_SESSION["posted_ro_office"],
								"dst_usr_cd"			=>	$dst_usr,
								"stage_smpl_flag"		=>	"VS",
								"tran_date"				=>	$tran_date,
								"user_code"				=>	$_SESSION["user_code"],
								"stage_smpl_cd"			=>	$sample_code,
								"stage"					=>	"7");
							}
						}

						$workflowEntity = $this->Workflow->newEntity($workflow_data);

						$this->Workflow->save($workflowEntity);

						$_SESSION["loc_id"] = $_SESSION["posted_ro_office"];

						$_SESSION["loc_user_id"] = $_SESSION["user_code"];

						$date = date("Y/m/d");

						$ogrsample3 = $query->fetchAll('assoc');

						$ogrsample_code = $ogrsample3[0]['org_sample_code'];


						if ($_SESSION['user_flag']=='RAL') {

							if (trim($result_flg)=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FS',
													remark ='$remark',
													grade_user_cd=".$_SESSION['user_code'].",
													grade_user_flag='".$_SESSION['user_flag']."',
													grade_user_loc_id='".$_SESSION['posted_ro_office']."',
													ral_anltc_rslt_rcpt_dt='$tran_date'
													WHERE category_code= '$category_code'
													AND commodity_code = '$commodity_code'
													AND org_sample_code = '$sample_code'
													AND display = 'Y' ");

							}
							else
							{

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FG',
														remark ='$remark',
														grade_user_cd=".$_SESSION['user_code'].",
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														ral_anltc_rslt_rcpt_dt='$tran_date'
														WHERE category_code= '$category_code'
														AND commodity_code = '$commodity_code'
														AND org_sample_code = '$sample_code'
														AND display = 'Y' ");
							}

						}
						elseif ($_SESSION['user_flag']=='CAL')
						{

							if ($result_flg=='F') {

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='FC',
														remark ='$remark',
														grade_user_cd=".$_SESSION['user_code'].",
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														ral_anltc_rslt_rcpt_dt='$tran_date'
														WHERE category_code= '$category_code'
														AND commodity_code = '$commodity_code'
														AND org_sample_code = '$sample_code'
														AND display = 'Y' ");

							}
							else
							{

								// Add two new fileds to add subgrading value and inward grading date
								$conn->execute("UPDATE sample_inward SET status_flag='VS',
														remark ='$remark',
														grade_user_cd='".$_SESSION['user_code']."',
														grade_user_flag='".$_SESSION['user_flag']."',
														grade_user_loc_id='".$_SESSION['posted_ro_office']."',
														cal_anltc_rslt_rcpt_dt='$tran_date'
														WHERE category_code= '$category_code'
														AND commodity_code = '$commodity_code'
														AND org_sample_code = '$sample_code'
														AND display = 'Y' ");
							}
						}

						//call to the common SMS/Email sending method
						$this->loadModel('DmiSmsEmailTemplates');
						//$this->DmiSmsEmailTemplates->sendMessage(2017,$sample_code);

						/* Change forward to RAL officer flash message,*/

						if ($_SESSION['user_flag']=='RAL') {

							$message ='The results have been finalized and forwarded to RAL,Office Incharge';
							// exit;

						} elseif ($_SESSION['user_flag']=='CAL') {

							$message = 'The results have been finalized and forwarded to CAL,Office Incharge';
							//echo '#The results have been finalized and forwarded to CAL,Office Incharge#';
							/* To disaply the message, after save the grading by inward officer.*/
							// exit;

						} else {
							$message = 'Record Save Sucessfully!';
							// exit;
						}
				}

			}

		}
		// else{

		// 	echo "Sorry.. You don't have  forward to oic";
		// }

			//$message = 'The results have been finalized and forwarded to CAL,Office Incharge';
			$message_theme = 'success';
			$redirect_to = 'ilc_finalized_samples';

			// set variables to show popup messages from view file
			$this->set('message',$message);
			$this->set('message_theme',$message_theme);
			$this->set('redirect_to',$redirect_to);

	}

	
	// 21-03-2023 for saving zscore  ande selected options
	public function saveFinalZscore(){

		$this->autoRender = false;
		$this->loadmodel('IlcCalculateZscores');
		$this->loadmodel('IlcFinalZscores');
		$this->loadmodel('IlcSaveTestParameters');
		$this->loadModel('FinalTestResult');
		$this->loadComponent('Ilc');

		
		$sample_code=$this->Session->read('grading_sample_code');
		
		$this->set('samples_list',array($sample_code=>$sample_code));
		$this->set('stage_sample_code',$sample_code);//for hidden field, to use common script


		$org_sample_code 	 = $_POST['stage_sample_code'];
		$testArr 	 		 = json_decode($_POST['testArr']);
		$sampleArr 	 		 = json_decode($_POST['sampleArr']);
		$org_val 	 		 = $_POST['org_val'];
		$zscore				 = $_POST['zscore'];
		$date 		 		 = date('Y-m-d H:i:s');
			
	
		
		$i=0;
		$zscoreval = array();
		$zscorearr = array();
		foreach($testArr as $row1) {
			
			

			$j=0;
			$orignal_value = array();
			foreach($sampleArr as $sample) {
			

				$date = date('Y-m-d H:i:s');
				//added a line for updated status according to status (0,1) done 05/04/2023 by shreeya
				$this->IlcFinalZscores->updateAll(array('status' => 0,'modified'=>"$date"),array('org_sample_code' => $org_sample_code));
				

				$zscoreval[] = array(

						'org_sample_code' 		=> $org_sample_code,
						'org_val'				=> $org_val[$i][$j],
						'test_name'				=> $testArr[$i],
						'zscore'				=> $zscore[$i][$j],
						'created'				=> $date,
						'modified'				=> $date,
						'sample_code' 			=> '123', //new feild added for saved zscore  By Sheya - Date[03-05-2023]
						'test_code'		  		=> '123', //new feild added for saved zscore
						'lab_name'		  		=> 'test',//new feild added for saved zscore
						'status'				=>1

				
				);
				$j++;
			}
		

			if(!empty($orignal_value)){

				$zscorearr[$i] = $orignal_value;
			}
			
			$i++;
		}
		
	
		//creating entities for array
		$SaveZscor = $this->IlcFinalZscores->newEntities($zscoreval);
		//saving data in loop
		foreach($SaveZscor as $list){	

			$this->IlcFinalZscores->save($list);	
		}

	}	

	//inner sub sample list of report with z score model
	public function ilcSampleZscoreRedirect($sample_code){

		$this->Session->write('grading_sample_code',$sample_code);
		$this->redirect(array('controller'=>'FinalGrading','action'=>'ilcAvailableSampleZscore'));
	}

	//outer sample of ILC main sample
	//added for show the finalized  list of ilc zscore on 25/07/2022 by Shreeya
	public function ilcFinalizedZscore(){

		$this->viewBuilder()->setLayout('admin_dashboard');
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		$this->loadModel('IlcOrgSmplcdMaps');

		$conn = ConnectionManager::get('default');

		$query2 = $conn->execute("SELECT si.org_sample_code, w.stage_smpl_cd,mc.commodity_name, st.sample_type_desc, w.tran_date,w.stage_smpl_flag,si.status_flag,ml.ro_office
				FROM sample_inward AS si
				INNER JOIN m_sample_type AS st ON si.sample_type_code=st.sample_type_code
				INNER JOIN dmi_ro_offices AS ml ON ml.id=si.loc_id
				INNER JOIN m_commodity AS mc ON si.commodity_code=mc.commodity_code
				INNER JOIN workflow AS w ON w.org_sample_code=si.org_sample_code
				WHERE  w.stage_smpl_flag IN('FGIO','VS') AND w.stage_smpl_flag!='FG' 
				AND w.stage_smpl_cd NOT IN ('','blank') AND si.sample_type_code=9 AND si.entry_type IS NULL 
				AND si.remark_officeincharg IS NULL AND si.remark_officeincharg_dt IS NULL"); // added the condition remark and remark date [07-07-2023]
				//w.stage_smpl_cd NOT IN ('','blank') added by shankhpal for empty sample code

		$result = $query2->fetchAll('assoc');
		$this->set('result',$result);


	

	}


	//added new method to get IWO grades on OIC window, to show default selected
	//on 14-06-2023 by Amol, called through ajax
	public function getIwoGrade(){

		$smpl_cd = $_POST['smpl_cd'];
		//get inward officer grade for this sample
		$this->loadModel('SampleInward');
		$this->loadModel('Workflow');
		//get original sample code
		$getOrgCode = $this->Workflow->find('all',array('fields'=>array('org_sample_code'),'conditions'=>array('stage_smpl_cd IS'=>$smpl_cd)))->first();

		$getIwoGrades = $this->SampleInward->find('all',array('fields'=>array('inward_grade','sub_grad_check_iwo'),'conditions'=>array('org_sample_code IS'=>$getOrgCode['org_sample_code'])))->first();

		echo '#'.json_encode(array($getIwoGrades['inward_grade'],$getIwoGrades['sub_grad_check_iwo'])).'#';
		exit;
	}


}
?>
