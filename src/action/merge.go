package action

import (
	"fmt"
	"github.com/easysoft/z/src/model"
	commService "github.com/easysoft/z/src/service/comm"
	gitlabService "github.com/easysoft/z/src/service/gitlab"
	jenkinsService "github.com/easysoft/z/src/service/jenkins"
	scmService "github.com/easysoft/z/src/service/scm"
	zentaoService "github.com/easysoft/z/src/service/zentao"
	constant "github.com/easysoft/z/src/utils/const"
	fileUtils "github.com/easysoft/z/src/utils/file"
	i118Utils "github.com/easysoft/z/src/utils/i118"
	logUtils "github.com/easysoft/z/src/utils/log"
	"path/filepath"
	"strings"
)

func PreMerge(srcBranchDir, distBranchName string) (resp model.ZentaoMergeResponse, err error) {
	if srcBranchDir == "" {
		srcBranchDir = fileUtils.GetWorkDir()
	}

	commService.GetConfig()

	conf := commService.GetConfig()
	resp, err = PreMergeAllSteps(srcBranchDir, distBranchName, conf, false, false, false)

	return
}

func PreMergeAllSteps(srcBranchDir, distBranchName string, zentaoSite model.ZentaoSite, execCIBuild, waitBuildCompleted, createGitLabMr bool) (
	resp model.ZentaoMergeResponse, err error) {

	outMerge, outDiff, repoUrl, srcBranchName, distBranchDir, errCombine :=
		scmService.CombineCodesLocally(srcBranchDir, distBranchName)

	mergerInfo := model.ZentaoMerge{
		MergeResult: errCombine == nil,
		MergeMsg:    strings.Join(outMerge, "\n"),
		DiffMsg:     strings.Join(outDiff, "\n"),
	}

	zentaoBuild, errGetRepo := zentaoService.GetRepoDefaultBuild(repoUrl, zentaoSite)
	if errGetRepo != nil {
		logUtils.Errorf(i118Utils.Sprintf("get_repo_default_build_fail", errGetRepo.Error()))
		return
	}

	var uploadResult model.UploadResponse
	var uploadErr error

	// upload file
	if errGetRepo == nil && errCombine == nil {
		zipFile := filepath.Join(filepath.Dir(distBranchDir), "result.zip")
		fileUtils.ZipFiles(zipFile, distBranchDir)

		files := []string{""}
		params := map[string]string{"account": zentaoBuild.FileServerAccount, "password": zentaoBuild.FileServerPassword}
		uploadResult, uploadErr = fileUtils.Upload(zentaoBuild.FileServerUrl, files, params)
		mergerInfo.UploadMsg = uploadErr.Error()

		if uploadErr != nil {
			logUtils.Errorf(i118Utils.Sprintf("upload_combined_code_fail", uploadErr.Error()))
			return
		}
	}

	// exec build on CI platform
	if execCIBuild && errCombine == nil && uploadErr == nil {
		if zentaoBuild.CIServerType == constant.Jenkins {
			jenkinsSite := model.JenkinsSite{
				Url: zentaoBuild.CIServerUrl, Account: zentaoBuild.CIServerAccount, Token: zentaoBuild.CIServerToken}

			queueId, buildId, errBuildJob := jenkinsService.BuildJob(zentaoBuild.CIJobName, uploadResult.FileDir, jenkinsSite, waitBuildCompleted)

			mergerInfo.CIJobName = zentaoBuild.CIJobName
			mergerInfo.CIQueueId = queueId
			mergerInfo.CIBuildId = buildId

			if errBuildJob != nil {
				logUtils.Errorf(i118Utils.Sprintf("build_jenkins_job_fail", errBuildJob.Error()))
				return
			}
		}
	}

	// create MR in gitlab
	if createGitLabMr {
		gitlabSite := model.GitLabSite{Url: zentaoBuild.GitLabUrl, Token: zentaoBuild.GitLabToken}
		mr, errCreateMr := gitlabService.CreateMr(zentaoBuild.GitLabProjectId, srcBranchName, distBranchName, gitlabSite)

		if errCreateMr != nil {
			mergerInfo.CreateMrMsg = errCreateMr.Error()
			logUtils.Errorf(i118Utils.Sprintf("create_gitlab_mr_fail", errCreateMr.Error()))
			return

		} else {
			mergerInfo.CreateMrMsg = fmt.Sprintf("success to create mr %s", mr.Title)
		}
	}

	resp, err = zentaoService.SubmitMergeInfo(mergerInfo, zentaoSite)

	return
}
