<?php
/**
 * This helper builds the request body for a /mail/send API call
 * 
 * PHP Version - 5.6, 7.0, 7.1, 7.2
 *
 * @package   SendGrid\Mail
 * @author    Elmer Thomas <dx@sendgrid.com>
 * @copyright 2018 SendGrid
 * @license   https://opensource.org/licenses/MIT The MIT License
 * @version   GIT: <git_id>
 * @link      http://packagist.org/packages/sendgrid/sendgrid 
 */
namespace SendGrid\Mail;

/**
 * This class is used to construct a request body for the /mail/send API call
 * 
 * @package SendGrid\Mail
 */
class Mail implements \JsonSerializable
{
    // @var From Email address of the sender
    private $from;

    // @var Subject Subject of the email
    private $subject;

    // @var Content[] Content(s) of the email
    private $contents;

    // @var Attachment[] Email attachments
    private $attachments;

    // @var TemplateId Id of a template that you would like to use
    private $template_id;

    // @var Section[] Key/value pairs that define block sections of code 
    // to be used as substitutions
    private $sections;

    // @var Header[] Header names and the value to substitute for them
    private $headers;

    // @var Category[] Category names for this message
    private $categories;

    // @var CustomArg[] Values that are specific to the entire send that 
    // will be carried along with the email and its activity data
    private $custom_args;

    // @var Substitution[] Substitutions that will apply to the text and html 
    // content of the body of your email, in addition to the subject and reply-to 
    // parameters
    private $substitutions;

    // @var SendAt A unix timestamp allowing you to specify when you want your 
    // email to be delivered
    private $send_at;

    // @var BatchId This ID represents a batch of emails to be sent at the same time
    private $batch_id;

    // @var ASM Specifies how to handle unsubscribes
    private $asm;

    // @var IpPoolName The IP Pool that you would like to send this email from
    private $ip_pool_name;

    // @var MailSettings A collection of different mail settings that you can use 
    // to specify how you would like this email to be handled
    private $mail_settings;

    // @var TrackingSettings Settings to determine how you would like to track the 
    // metrics of how your recipients interact with your email
    private $tracking_settings;

    // @var ReplyTo Email to be use when replied to
    private $reply_to;

    // @var Personalization Messages and their metadata
    private $personalization;

    const   VERSION = "7.0.0";

    /**
     * If passing parameters into this constructor include
     * $from, $to, $subject, $plainTextContent and
     * $htmlContent at a minimum. In that case, a Personalization
     * object will be created for you.
     *
     * @param From|null              $from                Email address of the sender
     * @param To|To[]|null           $to                  Recipient(s) email 
     *                                                    address(es)
     * @param Subject|Subject[]|null $subject             Subject(s)
     * @param PlainTextContent|null  $plainTextContent    Plain text version of 
     *                                                    content
     * @param HtmlContent|null       $htmlContent         Html version of content
     * @param Substitution[]|null    $globalSubstitutions Substitutions for entire
     *                                                    email
     */
    public function __construct(
        $from = null,
        $to = null,
        $subject = null,
        $plainTextContent = null,
        $htmlContent = null,
        array $globalSubstitutions = null
    ) { 
        if (!isset($from)
            && !isset($to)
            && !isset($subject)
            && !isset($plainTextContent)
            && !isset($htmlContent)
            && !isset($globalSubstitutions)
        ) {
            $this->personalization[] = new Personalization();
            return;
        }
        if(isset($from)) $this->setFrom($from);
        if(isset($to)) {
            if (!is_array($to)) {
                $to = [ $to ];
            }
            $subjectCount = 0;
            $personalization = new Personalization();
            foreach ($to as $email) {
                if ($subs = $email->getSubstitions()) {
                    $personalization = new Personalization();
                }
                $personalization->addTo($email);
                if ($subs = $email->getSubstitions()) {
                    foreach ($subs as $key => $value) {
                        $personalization->addSubstitution($key, $value);
                    }
                }
                if ($email->getSubject()) {
                    $personalization->setSubject($email->getSubject());
                }
                if (is_array($subject)) {
                    if ($subjectCount < sizeof($subject)) {
                        $personalization->setSubject($subject[$subjectCount]);
                    }
                    $subjectCount++;
                }
                if (is_array($globalSubstitutions)) {
                    foreach ($globalSubstitutions as $key => $value) {
                        $personalization->addSubstitution($key, $value);
                    }
                }
                if ($subs = $email->getSubstitions()) {
                    $this->addPersonalization($personalization);  
                }
            }    
            if (!$subs = $email->getSubstitions()) {
                $this->addPersonalization($personalization);  
            }      
        }
        if (isset($subject)) {
            if (!is_array($subject)) {
                $this->setSubject($subject);
            }
        }
        if(isset($plainTextContent)) $this->addContent($plainTextContent);
        if(isset($htmlContent)) $this->addContent($htmlContent);
    }

    /**
     * Adds a To, Cc or Bcc object to a Personalization object
     *
     * @param string               $emailType            Object type name: To, Cc 
     *                                                   or Bcc
     * @param string               $email                Recipient email address
     * @param string|null          $name                 Recipient name
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */    
    private function addRecipientEmail(
        $emailType,
        $email,
        $name = null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $personalizationFunctionCall = "add".$emailType;
        $emailType = "\SendGrid\Mail\\".$emailType;
        if (!$email instanceof $emailType) {
            $email = new $emailType($email, $name);
        }
        if ($personalization != null) {
            $personalization->$personalizationFunctionCall($email);
            $this->addPersonalization($personalization);
            return;
        } else {
            if ($this->personalization[0] != null) {
                $this->personalization[0]->$personalizationFunctionCall($email);
                return;
            } elseif ($this->personalization[$personalizationIndex] != null) {
                $this->personalization[
                    $personalizationIndex]->$personalizationFunctionCall($email);
                return;
            } else {
                $personalization = new Personalization();
                $personalization->$personalizationFunctionCall($email);
                if (($personalizationIndex != 0)
                    && ($this->getPersonalizationCount() <= $personalizationIndex)
                ) {
                    $this->personalization[$personalizationIndex] = $personalization;
                } else {
                    $this->addPersonalization($personalization);
                }
                return;
            }
        }
    }

    /**
     * Adds an array of To, Cc or Bcc objects to a Personalization object
     *
     * @param string               $emailType            Object type name: To, Cc 
     *                                                   or Bcc
     * @param To[]|Cc[]|Bcc[]      $emails               Array of email recipients
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A Personalization object
     * 
     * @return null
     */      
    private function addRecipientEmails(
        $emailType,
        $emails,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $emailFunctionCall = "add".$emailType;

        if (current($emails) instanceof EmailAddress) {
            foreach ($emails as $email) {
                $this->$emailFunctionCall(
                    $email,
                    $name = null,
                    $personalizationIndex,
                    $personalization
                );
            }
        } else {
            foreach ($emails as $email => $name) {
                $this->$emailFunctionCall(
                    $email,
                    $name,
                    $personalizationIndex,
                    $personalization
                );
            }
        }
    }    

    /**
     * Add a Personalization object to the Mail object
     *
     * @param Personalization $personalization A Personalization object
     * 
     * @return null
     */      
    public function addPersonalization($personalization)
    {
        $this->personalization[] = $personalization;
    }

    // @return Personalization[] Array of Personalization objects
    public function getPersonalizations()
    {
        return $this->personalization;
    }

    /**
     * Adds an email recipient to a Personalization object
     *
     * @param string|To            $to                   Email address or To object
     * @param string               $name                 Recipient name
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */   
    public function addTo(
        $to,
        $name = null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if ($to instanceof To) {
            $name = $to->getName();
            $to = $to->getEmailAddress();
        }
        $this->addRecipientEmail(
            "To",
            $to,
            $name,
            $personalizationIndex,
            $personalization
        );
    }

    /**
     * Adds multiple email recipients to a Personalization object
     *
     * @param To[]|array           $toEmails             Array of To objects or 
     *                                                   key/value pairs of email 
     *                                                   address/recipient names
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */       
    public function addTos(
        $toEmails,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $this->addRecipientEmails(
            "To",
            $toEmails,
            $personalizationIndex,
            $personalization
        );
    }

    /**
     * Adds an email cc recipient to a Personalization object
     *
     * @param string|Cc            $cc                   Email address or Cc object
     * @param string               $name                 Recipient name
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */   
    public function addCc(
        $cc,
        $name = null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if ($cc instanceof Cc) {
            $name = $cc->getName();
            $cc = $cc->getEmailAddress();
        }
        $this->addRecipientEmail(
            "Cc",
            $cc,
            $name,
            $personalizationIndex,
            $personalization
        );
    }

    /**
     * Adds multiple email cc recipients to a Personalization object
     *
     * @param Cc[]|array           $ccEmails             Array of Cc objects or 
     *                                                   key/value pairs of email 
     *                                                   address/recipient names
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */  
    public function addCcs(
        $ccEmails,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $this->addRecipientEmails(
            "Cc",
            $ccEmails,
            $personalizationIndex,
            $personalization
        );
    }

    /**
     * Adds an email bcc recipient to a Personalization object
     *
     * @param string|Bcc           $bcc                  Email address or Bcc object
     * @param string               $name                 Recipient name
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */  
    public function addBcc(
        $bcc,
        $name = null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if ($bcc instanceof Bcc) {
            $name = $bcc->getName();
            $bcc = $bcc->getEmailAddress();  
        }
        $this->addRecipientEmail(
            "Bcc",
            $bcc,
            $name,
            $personalizationIndex,
            $personalization
        );
    }

    /**
     * Adds multiple email bcc recipients to a Personalization object
     *
     * @param Bcc[]|array          $bccEmails            Array of Bcc objects or 
     *                                                   key/value pairs of email 
     *                                                   address/recipient names
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */     
    public function addBccs(
        $bccEmails,
        $personalizationIndex = null,
        $personalization = null
    ) {
            $this->addRecipientEmails(
                "Bcc",
                $bccEmails,
                $personalizationIndex,
                $personalization
            );
    }

    /**
     * Add a subject to a Personalization or Mail object
     * 
     * If you don't provide a Personalization object or index, the
     * subject will be global to entire message. Note that 
     * subjects added to Personalization objects override
     * global subjects.
     *
     * @param string|Subject       $subject              Email subject
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */      
    public function setSubject(
        $subject,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if ($subject instanceof Subject) {
            $subject = $subject;
        } else {
            $subject = new Subject($subject);
        }

        if ($personalization != null) {
            $personalization->setSubject($subject);
            $this->addPersonalization($personalization);
            return;
        } 
        if ($personalizationIndex != null) {
            $this->personalization[$personalizationIndex]->setSubject($subject);
            return;
        }
        $this->setGlobalSubject($subject);
        return;
    }

    /**
     * Retrieve a subject attached to a Personalization object
     *
     * @param int|0 $personalizationIndex Index into an array of 
     *                                    existing Personalization 
     *                                    objects
     * 
     * @return string|Subject
     */  
    public function getSubject($personalizationIndex = 0)
    {
        return $this->personalization[$personalizationIndex]->getSubject();
    }

    /**
     * Add a header to a Personalization or Mail object
     * 
     * If you don't provide a Personalization object or index, the
     * header will be global to entire message. Note that 
     * headers added to Personalization objects override
     * global headers.
     *
     * @param string|Header        $key                  Key or Header object
     * @param string|null          $value                Value
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */   
    public function addHeader(
        $key,
        $value=null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $header = null;
        if ($key instanceof Header) {
            $h = $key;
            $header = new Header($h->getKey(), $h->getValue());
        } else {
            $header = new Header($key, $value);
        }
        if ($personalization != null) {
            $personalization->addHeader($header);
            $this->addPersonalization($personalization);
            return;
        } else {
            if ($this->personalization[0] != null) {
                $this->personalization[0]->addHeader($header);
            } elseif ($this->personalization[$personalizationIndex] != null) {
                $this->personalization[$personalizationIndex]->addHeader($header);
            } else {
                $personalization = new Personalization();
                $personalization->addHeader($header);
                if (($personalizationIndex != 0)
                    && ($this->getPersonalizationCount() <= $personalizationIndex)
                ) {
                    $this->personalization[$personalizationIndex] = $personalization;
                } else {
                    $this->addPersonalization($personalization);
                }
            }
            return;
        }
    }

    /**
     * Adds multiple headers to a Personalization or Mail object
     * 
     * If you don't provide a Personalization object or index, the
     * header will be global to entire message. Note that 
     * headers added to Personalization objects override
     * global headers.
     *
     * @param Header[]             $headers              Array of Header objects
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */       
    public function addHeaders(
        $headers,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if (current($headers) instanceof Header) {
            foreach ($headers as $header) {
                $this->addHeader($header);
            }
        } else {
            foreach ($headers as $key => $value) {
                $this->addHeader(
                    $key,
                    $value,
                    $personalizationIndex,
                    $personalization
                );
            }
        }
    }

    /**
     * Retrieve the headers (key/values) attached to a Personalization object
     *
     * @param int|0 $personalizationIndex Index into an array of 
     *                                    existing Personalization 
     *                                    objects
     * 
     * @return array
     */  
    public function getHeaders($personalizationIndex = 0)
    {
        return $this->personalization[$personalizationIndex]->getHeaders();
    }

    /**
     * Add a substitution to a Personalization or Mail object
     * 
     * If you don't provide a Personalization object or index, the
     * substitution will be global to entire message. Note that 
     * substitutions added to Personalization objects override
     * global substitutions.
     *
     * @param string|Substitution  $key                  Key or Substitution object
     * @param string|null          $value                Value
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */   
    public function addSubstitution(
        $key,
        $value=null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $substitution = null;
        if ($key instanceof Substitution) {
            $s = $key;
            $substitution = new Substitution($s->getKey(), $s->getValue());
        } else {
            $substitution = new Substitution($key, $value);
        }
        if ($personalization != null) {
            $personalization->addSubstitution($substitution);
            $this->addPersonalization($personalization);
            return;
        } else {
            if ($this->personalization[0] != null) {
                $this->personalization[0]->addSubstitution($substitution);
            } elseif ($this->personalization[$personalizationIndex] != null) {
                $this->personalization[
                    $personalizationIndex]->addSubstitution($substitution);
            } else {
                $personalization = new Personalization();
                $personalization->addSubstitution($substitution);
                if (($personalizationIndex != 0)
                    && ($this->getPersonalizationCount() <= $personalizationIndex)
                ) {
                    $this->personalization[$personalizationIndex] = $personalization;
                } else {
                    $this->addPersonalization($personalization);
                }
            }
            return;
        }
    }

    /**
     * Adds multiple substitutions to a Personalization or Mail object
     * 
     * If you don't provide a Personalization object or index, the
     * substitution will be global to entire message. Note that 
     * substitutions added to Personalization objects override
     * global headers.
     *
     * @param Substitution[]       $substitutions        Array of Substitution objects
     * @param int|null             $personalizationIndex Index into an array of 
     *                                                   existing Personalization 
     *                                                   objects
     * @param Personalization|null $personalization      A pre-created 
     *                                                   Personalization object
     * 
     * @return null
     */ 
    public function addSubstitutions(
        $substitutions,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if (current($substitutions) instanceof Substitution) {
            foreach ($substitutions as $substitution) {
                $this->addSubstitution($substitution);
            }
        } else {
            foreach ($substitutions as $key => $value) {
                $this->addSubstitution(
                    $key,
                    $value,
                    $personalizationIndex,
                    $personalization
                );
            }
        }
    }

    /**
     * Retrieve the substitutions (key/values) attached to a Personalization object
     *
     * @param int|0 $personalizationIndex Index into an array of 
     *                                    existing Personalization 
     *                                    objects
     * 
     * @return array
     */  
    public function getSubstitutions($personalizationIndex = 0)
    {
        return $this->personalization[$personalizationIndex]->getSubstitutions();
    }

    public function addCustomArg(
        $key,
        $value=null,
        $personalizationIndex = null,
        $personalization = null
    ) {
        $custom_arg = null;
        if ($key instanceof CustomArg) {
            $ca = $key;
            $custom_arg = new CustomArg($ca->getKey(), $ca->getValue());
        } else {
            $custom_arg = new CustomArg($key, $value);
        }
        if ($personalization != null) {
            $personalization->addCustomArg($custom_arg);
            $this->addPersonalization($personalization);
            return;
        } else {
            if ($this->personalization[0] != null) {
                $this->personalization[0]->addCustomArg($custom_arg);
            } elseif ($this->personalization[$personalizationIndex] != null) {
                $this->personalization[$personalizationIndex]->addCustomArg($custom_arg);
            } else {
                $personalization = new Personalization();
                $personalization->addCustomArg($custom_arg);
                if (($personalizationIndex != 0)
                    && ($this->getPersonalizationCount() <= $personalizationIndex)
                ) {
                    $this->personalization[$personalizationIndex] = $personalization;
                } else {
                    $this->addPersonalization($personalization);
                }
            }
            return;
        }
    }

    public function addCustomArgs($custom_args)
    {
        if (current($custom_args) instanceof CustomArg) {
            foreach ($custom_args as $custom_arg) {
                $this->addCustomArg($custom_arg);
            }
        } else {
            foreach ($custom_args as $key => $value) {
                $this->addCustomArg($key, $value);
            }
        }
    }    

    public function getCustomArgs($personalizationIndex = 0)
    {
        return $this->personalization[$personalizationIndex]->getCustomArgs();
    }

    public function setSendAt(
        $send_at,
        $personalizationIndex = null,
        $personalization = null
    ) {
        if ($send_at instanceof SendAt) {
            $send_at = $send_at;
        } else {
            $send_at = new SendAt($send_at);
        }
        if ($personalization != null) {
            $personalization->setSendAt($send_at);
            $this->addPersonalization($personalization);
            return;
        } else {
            if ($this->personalization[0] != null) {
                $this->personalization[0]->setSendAt($send_at);
                return;
            } elseif ($this->personalization[$personalizationIndex] != null) {
                $this->personalization[$personalizationIndex]->setSendAt($send_at);
                return;
            } else {
                $personalization = new Personalization();
                $personalization->setSendAt($send_at);
                if (($personalizationIndex != 0)
                    && ($this->getPersonalizationCount() <= $personalizationIndex)
                ) {
                    $this->personalization[$personalizationIndex] = $personalization;
                } else {
                    $this->addPersonalization($personalization);
                }
                return;
            }
        }
    }

    public function getSendAt($personalizationIndex = 0)
    {
        return $this->personalization[$personalizationIndex]->getSendAt();
    }  

    public function setFrom($email, $name=null)
    {
        if ($email instanceof From) {
            $this->from = $email;
        } else {
            $this->from = new From($email, $name);
        }
        return;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function setReplyTo($email, $name=null)
    {
        if ($email instanceof ReplyTo) {
            $this->reply_to = $email;
        } else {
            $this->reply_to = new ReplyTo($email, $name);
        }
        return;
    }

    public function getReplyTo()
    {
        return $this->reply_to;
    }

    public function setGlobalSubject($subject)
    {
        if ($subject instanceof Subject) {
            $subject = $subject;
        } else {
            $subject = new Subject($subject);
        }
        $this->subject = $subject;
    }

    public function getGlobalSubject()
    {
        return $this->subject;
    }

    public function addContent($type, $value = null)
    {
        if ($type instanceof Content) {
            $content = $type;
        } else {
            $content = new Content($type, $value);
        }
        $this->contents[] = $content;
    }

    public function addContents($contents)
    {
        if (current($contents) instanceof Content) {
            foreach ($contents as $content) {
                $this->addContent($content);
            }
        } else {
            foreach ($contents as $key => $value) {
                $this->addContent($key, $value);
            }
        }
    }    

    public function getContents()
    {
        // TODO: Ensure text/plain is always first
        return $this->contents;
    }

    public function addAttachment(
        $attachment,
        $type = null,
        $filename = null,
        $disposition = null,
        $content_id = null
    ) {
        if ($attachment instanceof Attachment) {
            $attachment = $attachment;
        } elseif (is_array($attachment)) {
            $attachment = new Attachment(
                $attachment[0],
                $attachment[1],
                $attachment[2],
                $attachment[3],
                $attachment[4]
            );
        } else {
            $attachment = new Attachment(
                $attachment,
                $type,
                $filename,
                $disposition,
                $content_id
            );
        }
        $this->attachments[] = $attachment;
    }

    public function addAttachments($attachments)
    {
        foreach ($attachments as $attachment) {
            $this->addAttachment($attachment);
        }
    } 

    public function getAttachments()
    {
        return $this->attachments;
    }

    public function setTemplateId($template_id)
    {
        if ($template_id instanceof TemplateId) {
            $template_id = $template_id;
        } else {
            $template_id = new TemplateId($template_id);
        }
        $this->template_id = $template_id;
    }

    public function getTemplateId()
    {
        return $this->template_id;
    }

    public function addSection($key, $value=null)
    {
        if ($key instanceof Section) {
            $section = $key;
            $this->sections[$section->getKey()]
                = $section->getValue();
            return;
        }
        $this->sections[$key] = (string)$value;
    }

    public function addSections($sections)
    {
        if (current($sections) instanceof Section) {
            foreach ($sections as $section) {
                $this->addSection($section);
            }
        } else {
            foreach ($sections as $key => $value) {
                $this->addSection($key, $value);
            }
        }
    }

    public function getSections()
    {
        return $this->sections;
    }

    public function addGlobalHeader($key, $value=null)
    {
        if ($key instanceof Header) {
            $header = $key;
            $this->headers[$header->getKey()]
                = $header->getValue();
            return;
        }
        $this->headers[$key] = (string)$value;
    }

    public function addGlobalHeaders($headers)
    {
        if (current($headers) instanceof Header) {
            foreach ($headers as $header) {
                $this->addGlobalHeader($header);
            }
        } else {
            foreach ($headers as $key => $value) {
                $this->addGlobalHeader($key, $value);
            }
        }
    }

    public function getGlobalHeaders()
    {
        return $this->headers;
    }

    public function addGlobalSubstitution($key, $value=null)
    {
        if ($key instanceof Substitution) {
            $substitution = $key;
            $this->substitutions[$substitution->getKey()]
                = $substitution->getValue();
            return;
        }
        $this->substitutions[$key] = $value;
    }

    public function addGlobalSubstitutions($substitutions)
    {
        if (current($substitutions) instanceof Substitution) {
            foreach ($substitutions as $substitution) {
                $this->addGlobalSubstitution($substitution);
            }
        } else {
            foreach ($substitutions as $key => $value) {
                $this->addGlobalSubstitution($key, $value);
            }
        }
    }

    public function getGlobalSubstitutions()
    {
        return $this->substitutions;
    }

    public function addCategory($category)
    {
        if ($category instanceof Category) {
            $category = $category;
        } else {
            $category = new Category($category);
        }
        $this->categories[] = $category;
    }

    public function addCategories($categories)
    {
        foreach ($categories as $category) {
            $this->addCategory($category);
        }
        return;
    }

    public function getCategories()
    {
        return $this->categories;
    }

    public function addGlobalCustomArg($key, $value=null)
    {
        if ($key instanceof CustomArg) {
            $custom_arg = $key;
            $this->custom_args[$custom_arg->getKey()]
                = $custom_arg->getValue();
            return;
        }
        $this->custom_args[$key] = (string)$value;
    }

    public function addGlobalCustomArgs($custom_args)
    {
        if (current($custom_args) instanceof CustomArg) {
            foreach ($custom_args as $custom_arg) {
                $this->addGlobalCustomArg($custom_arg);
            }
        } else {
            foreach ($custom_args as $key => $value) {
                $this->addGlobalCustomArg($key, $value);
            }
        }
    }

    public function getGlobalCustomArgs()
    {
        return $this->custom_args;
    }  

    public function setGlobalSendAt($send_at)
    {
        if ($send_at instanceof SendAt) {
            $send_at = $send_at;
        } else {
            $send_at = new SendAt($send_at);
        }
        $this->send_at = $send_at;
    }

    public function getGlobalSendAt()
    {
        return $this->send_at;
    }

    public function setBatchId($batch_id)
    {
        if ($batch_id instanceof BatchId) {
            $batch_id = $batch_id;
        } else {
            $batch_id = new BatchId($batch_id);
        }
        $this->batch_id = $batch_id;
    }

    public function getBatchId()
    {
        return $this->batch_id;
    }

    public function setAsm($group_id, $groups_to_display=null)
    {
        if ($group_id instanceof Asm) {
            $asm = $group_id;
            $this->asm = $asm;
        } else {
            $this->asm = new Asm($group_id, $groups_to_display);
        }
        return;
    }

    public function getAsm()
    {
        return $this->asm;
    }

    public function setIpPoolName($ip_pool_name)
    {
        if ($ip_pool_name instanceof IpPoolName) {
            $this->ip_pool_name = $ip_pool_name->getIpPoolName();
        } else {
            $this->ip_pool_name = new IpPoolName($ip_pool_name);
        }
        
    }

    public function getIpPoolName()
    {
        return $this->ip_pool_name;
    }

    public function setMailSettings($mail_settings)
    {
        $this->mail_settings = $mail_settings;
    }

    public function getMailSettings()
    {
        return $this->mail_settings;
    }

    public function setBccSettings($enable=null, $email=null)
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setBccSettings($enable, $email);
    }

    public function enableBypassListManagement()
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setBypassListManagement(true);
    }

    public function disableBypassListManagement()
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setBypassListManagement(false);
    }

    public function setFooter($enable=null, $text=null, $html=null)
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setFooter($enable, $text, $html);
    }

    public function enableSandBoxMode()
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setSandBoxMode(true);
    }

    public function disableSandBoxMode()
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setSandBoxMode(false);
    }

    public function setSpamCheck($enable=null, $threshold=null, $post_to_url=null)
    {
        if (!$this->mail_settings instanceof MailSettings) {
            $this->mail_settings = new MailSettings();
        }
        $this->mail_settings->setSpamCheck($enable, $threshold, $post_to_url);
    }

    public function setTrackingSettings($tracking_settings)
    {
        $this->tracking_settings = $tracking_settings;
    }

    public function getTrackingSettings()
    {
        return $this->tracking_settings;
    }

    public function setClickTracking($enable=null, $enable_text=null)
    {
        if (!$this->tracking_settings instanceof TrackingSettings) {
            $this->tracking_settings = new TrackingSettings();
        }
        $this->tracking_settings->setClickTracking($enable, $enable_text);
    }

    public function setOpenTracking($enable=null, $substitution_tag=null)
    {
        if (!$this->tracking_settings instanceof TrackingSettings) {
            $this->tracking_settings = new TrackingSettings();
        }
        $this->tracking_settings->setOpenTracking($enable, $substitution_tag);
    }

    public function setSubscriptionTracking($enable=null, $text=null, $html=null, $substitution_tag=null)
    {
        if (!$this->tracking_settings instanceof TrackingSettings) {
            $this->tracking_settings = new TrackingSettings();
        }
        $this->tracking_settings->setSubscriptionTracking($enable, $text, $html, $substitution_tag);
    }

    public function setGanalytics(
        $enable=null,
        $utm_source=null,
        $utm_medium=null,
        $utm_term=null,
        $utm_content=null,
        $utm_campaign=null
    ) {
        if (!$this->tracking_settings instanceof TrackingSettings) {
            $this->tracking_settings = new TrackingSettings();
        }
        $this->tracking_settings->setGanalytics(
            $enable,
            $utm_source,
            $utm_medium,
            $utm_term,
            $utm_content,
            $utm_campaign
        );
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'personalizations'  => $this->getPersonalizations(),
                'from'              => $this->getFrom(),
                'reply_to'          => $this->getReplyTo(),
                'subject'           => $this->getGlobalSubject(),
                'content'           => $this->getContents(),
                'attachments'       => $this->getAttachments(),
                'template_id'       => $this->getTemplateId(),
                'sections'          => $this->getSections(),
                'headers'           => $this->getGlobalHeaders(),
                'categories'        => $this->getCategories(),
                'custom_args'       => $this->getGlobalCustomArgs(),
                'send_at'           => $this->getGlobalSendAt(),
                'batch_id'          => $this->getBatchId(),
                'asm'               => $this->getASM(),
                'ip_pool_name'      => $this->getIpPoolName(),
                'substitutions'     => $this->getGlobalSubstitutions(),
                'mail_settings'     => $this->getMailSettings(),
                'tracking_settings' => $this->getTrackingSettings()
            ],
            function ($value) {
                return $value !== null;
            }
        ) ?: null;
    }
}

// TODO: Make sure all returns are using their getters