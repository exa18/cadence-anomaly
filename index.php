<?php
$version = "1.0";

if(isset($_POST['action']) and $_POST['action'] == 'upload'){

    if(isset($_FILES['user_file']))
    {
        $files = $_FILES['user_file'];
		$url = $_FILES["user_file"]["tmp_name"]; 
    	$file_name = $_FILES["user_file"]["name"];
	}
	if(isset($_POST['baro'])) $baro=(int)$_POST['baro'];
	
	$xml=simplexml_load_file($url);
	$ext=strtolower(substr($file_name,-3));
	$cad=array();
	$type=0;

	if ($ext=='pwx') {
		/*
			PWX
		*/
		$type=1;
		foreach ($xml->workout->sample as $c) {
			$cad[] = (int)$c->cad;
		}
	}elseif ($ext=='tcx') {
		/*
			TCX
		*/
		$type=2;
		foreach ($xml->Activities->Activity->Lap as $v) {
			foreach ($v->Track->Trackpoint as $c) {
				$cad[] = (int)$c->Cadence;
			}
		}
	
	}elseif ($ext=='gpx') {
		/*
			GPX
		*/
		$type=3;
		foreach ($xml->trk->trkseg->trkpt as $v) {
			$cad[] = (int)$v->extensions->children('gpxtpx',true)->TrackPointExtension->cad;
		}
	
	}
	/*
		Fixing
	*/
	if ($type && count($cad)>0) {
		
		/*
			FIX : start
		*/
			$i=0;
			foreach ($cad as $k=>$v) {
				$freq[$v]+=1;
				$cad_avg_bef+=$v;
				if ($v>$cad_max_bef) { $cad_max_bef=$v; }
				if ($v>0) $i++;
			}
			$cad_avg_bef=round($cad_avg_bef/$i,0);
			ksort($freq);
	
			/*
				findTripple and zero
				
				search 3x255, np.
				255,255,255,0
				130,130,130,0
				...
			*/
			foreach ($cad as $k=>$val) {
			$z=$cad[$k-1];
			$a=$val;
			$b=$cad[$k+1];
			$c=$cad[$k+2];
			$d=$cad[$k+3];
				if ($a>0 && $b>0 && $c>0 && $z>0) {
					if ($b==$a && $c==$a && $d==0) {
					$cor_a[$k]="*A-255";
					}
				}
			}
			if (count($cor_a)){
			foreach ($cor_a as $k=>$val) {
				$z=$cad[$k-1];
				$a=$val;
				$b=$cad[$k+1];
				$c=$cad[$k+2];
					$a=round($z/2,0);
					$b=round($a/2,0);
					$c=round($b/2,0);
				$cad[$k]=$a;
				$cad[$k+1]=$b;
				$cad[$k+2]=$c;
			}
			}
			/*
				search only 255
			*/
			foreach ($cad as $k=>$val) {
			$z=$cad[$k-1];
			$a=$val;
			$b=$cad[$k+1];
			$c=$cad[$k+2];
				if ($a==255) {
					$cor_b[$k]="*B-255";
					if ($b==255) {
						$cor_b[$k+1]="*B";
						$i=round(abs($z-$c)/3,0);
						if ($z>$c) {
							$a = $z-$i;
							$b = $a-$i;
						}else{
							$a = $z+$i;
							$b = $a+$i;						
						}
					}else{
						$i=round(abs($z-$b)/2,0);
						$a = $z>$b?$z-$i:$z+$i;
					}
						$cad[$k]=$a;
						$cad[$k+1]=$b;
				}
			}
			/*
				Additional anomalies
				unfavorable values and sudden jumps
			*/
			foreach ($cad as $k=>$val) {
			$z=$cad[$k-1];
			$a=$val;
			$b=$cad[$k+1];
			$c=$cad[$k+2];
			$d=$cad[$k+3];
			$at = $a*1.1;
				if ($a>0 && $at<$b && ($c<$b || $d<$c)) {
					$cor_c[$k+1]="*C-".$b;
					if ($b==$c) {
						$cor_c[$k+2]="*C-".$c;
						$i=round(abs($a-$d)/3,0);
						if ($a>$d) {
							$b = $a-$i;
							$c = $b-$i;
						}else{
							$b = $a+$i;
							$c = $b+$i;						
						}				
					}else{
						$i=round(abs($a-$c)/2,0);
						$b = $a>$c?$a-$i:$a+$i;
					}
						$cad[$k+1]=$b;
						$cad[$k+2]=$c;
				}
			}
			/*
				Search for triggers beginning or ending non-zero
			*/
			foreach ($cad as $k=>$val) {
			$z=$cad[$k-1];
			$a=$val;
			$b=$cad[$k+1];
			$c=$cad[$k+2];
			$d=$cad[$k+3];
				if ( ( $a==$b && $a==$c && $b==$c && (($a+$b+$c)>0) ) && ($z>0 || $d>0) && ($z<=$a || $d<=$c) && $a!=$d) {
					$i=round(abs($z-$d)/4,0);
					if ( ($z<$d && ($d-$i)<$c) || ($z>$d && ($z-$i)<$a) ) {
					//	if ( round($d*1.1,0)>=$c || round($z*1.1,0)>=$a ) {
					$cor_d[$k]="*D-".$a;
					$cor_d[$k+1]="*D";
					$cor_d[$k+2]="*D";
					if ($z<$d) {
						$a=$z+$i;
						$b=$a+$i;
						$c=$b+$i;
					}else{
						$a=$z-$i;
						$b=$a-$i;
						$c=$b-$i;				
					}
						
					$cad[$k]=$a;
					$cad[$k+1]=$b;
					$cad[$k+2]=$c;
					//	}
					}
				}
			}
			/*
				Additional anomalies second pass
				unfavorable values and sudden jumps
			*/
			foreach ($cad as $k=>$val) {
			$z=$cad[$k-1];
			$a=$val;
			$b=$cad[$k+1];
			$c=$cad[$k+2];
			$d=$cad[$k+3];
			$at = $a*1.1;
				if ($a>0 && $at<$b && ($c<$b || $d<$c)) {
					$cor_e[$k+1]="*E-".$b;
					if ($b==$c) {
						$cor_e[$k+2]="*E-".$c;
						$i=round(abs($a-$d)/3,0);
						if ($a>$d) {
							$b = $a-$i;
							$c = $b-$i;
						}else{
							$b = $a+$i;
							$c = $b+$i;						
						}				
					}else{
						$i=round(abs($a-$c)/2,0);
						$b = $a>$c?$a-$i:$a+$i;
					}
						$cad[$k+1]=$b;
						$cad[$k+2]=$c;
				}
			}
			/*
				Repair start
			*/
			$k=0;
			$i=1;
			$a=$cad[$k];
			$b=$cad[$k+1];
			$c=$cad[$k+2];
			$d=$cad[$k+3];
			if ($a==$b) {
				$i++;
				if ($b==$c) {
					$i++;
					if ($c==$d) {
						$i++;
					}
				}
			}
			$z=$cad[$i];
			$i--;
			while ($i>=0) {
				$z=round($z/2,0);
				if ($i==0) $z=0;
				$cor_f[$i]="*F-".$cad[$i];
				$cad[$i]=$z;
				$i--;
			}
			
			$cad_fixed=(count($cor_a)*3)+count($cor_b)+count($cor_c)+count($cor_d)+count($cor_e)+count($cor_f);
			$max_cad=max($cad);
			$min_cad=min($cad);
		/*
			FIX :  end
		*/
		
		
	/*
		Get ready for download...
	*/
		$k=0;
		$device='Device';
		
		if ($type==1){
		/*
			PWX
		*/
			foreach ($xml->workout->sample as $c) {
				$c->cad=(string)$cad[$k];
			}
		}elseif ($type==2){
		/*
			TCX
		*/
			foreach ($xml->Activities->Activity->Lap as $v) {
				foreach ($v->Track->Trackpoint as $c) {
					$c->Cadence=(string)$cad[$k];
					$k++;
				}
			}
	
			$a=$xml->Activities->Activity;
			$t=' with barometer';
			if ($baro){
				if (isset($a->Creator)){
					$name=$a->Creator->Name;
					$a->Creator->Name = $name . $t;
				}else{
					$c = $a->addChild ('Creator');
					$c->addAttribute('xmlns:xsi:type', 'Device_t');
					$c->addChild ('Name', $device . $t );
				}
			}
			if (isset($a->Lap->Extensions->LX->MaxBikeCadence)){
				$a->Lap->Extensions->LX->MaxBikeCadence = $max_cad;
			}else{
				/*
				$ae='http://www.garmin.com/xmlschemas/ActivityExtension/v2';
				$Extensions = $a->Lap->addChild('Extensions');
				$LX = $Extensions->addChild('LX');
				$LX->addAttribute ('xmlns', $ae );
				$LX->addChild('MaxBikeCadence', $max_cad );
				*/
			}
		}elseif ($type==3){
		/*
			GPX
		*/
			foreach ($xml->trk->trkseg->trkpt as $v) {
				$v->extensions->children('gpxtpx',true)->TrackPointExtension->cad = (string)$cad[$k];
				$k++;
			}
			if ($baro){
				$t=' with Barometer';
				$c=$xml->attributes()->{'creator'};
				if (!empty($c)) {
					$xml->addAttribute('creator', $device . $t);
				}else{
					$xml->attributes()->{'creator'}=($c?$c:$device) . $t;
				}
			}
		}
		
		$dom = dom_import_simplexml($xml)->ownerDocument;
		$dom->formatOutput = true;
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.$file_name );
		echo $dom->saveXML();
	}else{
		echo "ERROR !!!";
	}
exit();
}
?>

<html>
<head>
	<meta http-equiv="content-type" content="text/html;charset=UTF-8" />
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />
	<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
	<script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

	<style type="text/css">
	body{width: 100%;height: 100%}
	.row{display: flex;justify-content: center; align-items: center;height:100%}
	.row>div{text-align:center}
	h3{color:#3cd}
	span.label{line-height:2;position: relative;top: -.2em}
	.inputfile{width:.1px;height:.1px;opacity:0;overflow:hidden;position:absolute;z-index:-1}
	.inputfile-1 + label{color:#fff;background-color:#3cd}
	.inputfile + label{max-width:80%;font-size:1.25rem;font-weight:700;text-overflow:ellipsis;white-space:nowrap;cursor:pointer;display:inline-block;overflow:hidden;padding:.625rem 1.25rem;margin-bottom:1.5rem}
	.inputfile + label svg{width:1em;height:1em;vertical-align:middle;fill:currentColor;margin-top:-.25em;margin-right:.25em}
	.txt-normal{font-weight:normal}
	.btn-default.btn-on.active, .btn-default.btn-off.active{background-color: #3cd;color: white}	
	.btn-switch .btn-default.btn-off.active{background-color: #777}
	#footer{margin:auto}
	</style>
	<script type="text/javascript">
	var ff = {
		labelval : 'Choose a file...',
		input:"file-1",
		labelchange : function(label){
			$('label[for="'+ff.input+'"] span').html(label);
		}
	};
	
	$(function(){
		ff.labelchange(ff.labelval);
			$('#'+ff.input).on('change', function(){
				var file = document.forms['form'][ff.input].files[0];
				//file.name == "photo.png"
				//file.type == "image/png"
				//file.size == 300821
				ff.labelchange(file.name);
				$('input[type="submit"]').removeClass('hidden');
				$('input[type="reset"]').removeClass('hidden');
			});
			$('input[type="reset"]').on('click', function(){
				$('input[type="submit"]').addClass('hidden');
				$('input[type="reset"]').addClass('hidden');
				ff.labelchange(ff.labelval);
			});
	});
	</script>
	<title>Cadence anomaly fixing TCX / PWX / GPX</title>
</head>
<body>
	<div class="container">
	<div class="row">
	<div class="col-xs-12">
	<h3>Cadence anomaly<br /><small>tool for fix it</small></h3>
	<p class="small">TCX / PWX / GPX&nbsp;&nbsp;<span class="label label-default">file format</span></p>
	<hr />
		<form id="form" method="post" action="index.php" enctype="multipart/form-data">
			<input type="hidden" name="action" value="upload" />
			<div class="form-group btn-switch">
			<div class="btn-group" data-toggle="buttons">
				<label class="txt-normal">&nbsp;include barometr</label>
				<label class="btn btn-default btn-off btn-xs ">
				<input type="radio" name="baro" value="0" />OFF</label>
				<label class="btn btn-default btn-on btn-xs active">
				<input type="radio" name="baro" value="1"  checked="checked" />ON</label>
            </div>
            </div>
			<input id="file-1" class="inputfile inputfile-1" type="file" name="user_file" />
				<label for="file-1" class="btn btn-default"><svg xmlns="#" width="20" height="17" viewBox="0 0 20 17">
					<path d="M10 0l-5.2 4.9h3.3v5.1h3.8v-5.1h3.3l-5.2-4.9zm9.3 11.5l-3.2-2.1h-2l3.4 2.6h-3.5c-.1 0-.2.1-.2.1l-.8 2.3h-6l-.8-2.2c-.1-.1-.1-.2-.2-.2h-3.6l3.4-2.6h-2l-3.2 2.1c-.4.3-.7 1-.6 1.5l.6 3.1c.1.5.7.9 1.2.9h16.3c.6 0 1.1-.4 1.3-.9l.6-3.1c.1-.5-.2-1.2-.7-1.5z"></path>
					</svg>&nbsp;<span></span></label>
					<br />
			<input class="btn btn-default hidden" type="reset" value="Clear" />
			<input class="btn btn-success hidden" type="submit" value="Convert" />
		</form>
		<hr />
		<div id="footer" class="small">powered by <a class="btn btn-default btn-xs" href="https://github.com/exa18/cadence-anomaly">GitHub</a></div>
	</div>
	</div>
	</div>
</body>
</html>
